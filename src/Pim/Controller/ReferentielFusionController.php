<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Entity\User;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\FusionType;
use App\Pim\Fusion\FicheFusionneur;
use App\Pim\Fusion\FusionEcran;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\ReferentielEcran;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Fusion de deux fiches doublons d'une même gamme : l'action de masse mène à
 * un écran de comparaison champ par champ (valeur la plus récente
 * présélectionnée, choix de la fiche survivante), puis la fusion applique les
 * valeurs retenues et archive l'absorbée avec la trace de la survivante.
 */
#[Route('/referentiel', name: 'app_mdm_')]
#[IsGranted('ROLE_BP_EDITOR')]
final class ReferentielFusionController extends AbstractController
{
    private const ULID = '[0-9A-HJKMNP-TV-Z]{26}';

    /** Départ depuis le bandeau d'actions : la sélection doit être exactement deux fiches d'une même gamme. */
    #[Route('/fusionner', name: 'referentiel_fusionner', methods: ['POST'])]
    public function fusionner(
        Request $request,
        ReferentielEcran $ecran,
        FicheRepository $fiches,
    ): Response {
        $retour = $this->redirectToRoute('app_mdm_referentiel_general', ['f' => $request->query->all('f')]);
        $form = $ecran->formSelectionSoumise($request->request->all('selection'));
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('warning', 'Sélection invalide, aucune fusion lancée.');

            return $retour;
        }
        /** @var array{ids: list<string>, tout: bool} $data */
        $data = $form->getData();
        if ($data['tout'] || 2 !== count($data['ids'])) {
            $this->addFlash('warning', 'La fusion demande de cocher exactement deux fiches.');

            return $retour;
        }
        [$idA, $idB] = $data['ids'];
        $types = $fiches->findTypesByIds($data['ids']);
        if (2 !== count($types) || count(array_unique($types)) > 1) {
            $this->addFlash('warning', 'Seules deux fiches d’une même gamme peuvent être fusionnées.');

            return $retour;
        }
        $type = TypeFiche::tryFrom((string) current($types));
        if (!in_array($type, FicheImportSchemaRegistry::supportedTypes(), true)) {
            $this->addFlash('warning', 'La fusion n’est pas disponible pour cette gamme.');

            return $retour;
        }

        return $this->redirectToRoute(
            'app_mdm_referentiel_fusion',
            ['a' => $idA, 'b' => $idB, 'f' => $request->query->all('f')],
            Response::HTTP_SEE_OTHER,
        );
    }

    /** Écran de comparaison : champs divergents, présélection « la plus récente l'emporte », choix du survivant. */
    #[Route('/fusion/{a}/{b}', name: 'referentiel_fusion', requirements: ['a' => self::ULID, 'b' => self::ULID], methods: ['GET'])]
    public function comparaison(
        Request $request,
        string $a,
        string $b,
        FusionEcran $ecran,
    ): Response {
        try {
            [$ficheA, $ficheB] = $ecran->paire($a, $b);
        } catch (\DomainException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('app_mdm_referentiel_general', ['f' => $request->query->all('f')]);
        }

        $groupes = $ecran->groupesDivergents($ficheA, $ficheB);
        $form = $ecran->formulaire($ficheA, $ficheB, $groupes, $this->generateUrl(
            'app_mdm_referentiel_fusion_appliquer',
            ['a' => $a, 'b' => $b, 'f' => $request->query->all('f')],
        ));

        return $this->render('mdm/referentiel_fusion.html.twig', [
            'fiche_a' => $ficheA,
            'fiche_b' => $ficheB,
            'groupes' => $groupes,
            'unions' => $ecran->unions($ficheA, $ficheB),
            'form_fusion' => $form->createView(),
            'url_retour' => $this->generateUrl('app_mdm_referentiel_general', ['f' => $request->query->all('f')]),
        ]);
    }

    /** Application de la fusion : verrou optimiste sur les versions vues à l'écran, puis fusionneur. */
    #[Route('/fusion/{a}/{b}/appliquer', name: 'referentiel_fusion_appliquer', requirements: ['a' => self::ULID, 'b' => self::ULID], methods: ['POST'])]
    public function appliquer(
        Request $request,
        string $a,
        string $b,
        FusionEcran $ecran,
        FicheFusionneur $fusionneur,
    ): Response {
        try {
            [$ficheA, $ficheB] = $ecran->paire($a, $b);
        } catch (\DomainException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('app_mdm_referentiel_general', ['f' => $request->query->all('f')]);
        }
        $ecranComparaison = $this->redirectToRoute('app_mdm_referentiel_fusion', ['a' => $a, 'b' => $b, 'f' => $request->query->all('f')]);

        // La liste des champs divergents est déterministe à partir des deux
        // fiches : le formulaire soumis se reconstruit à l'identique.
        $groupes = $ecran->groupesDivergents($ficheA, $ficheB);
        $form = $ecran->formulaire($ficheA, $ficheB, $groupes, '');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('warning', 'Formulaire invalide, fusion non appliquée.');

            return $ecranComparaison;
        }
        /** @var array<string, mixed> $data */
        $data = $form->getData();
        if ((string) $ficheA->version() !== $data['version_a'] || (string) $ficheB->version() !== $data['version_b']) {
            $this->addFlash('warning', 'Les fiches ont été modifiées depuis l’ouverture de l’écran : revérifiez les valeurs proposées.');

            return $ecranComparaison;
        }

        $survivant = 'b' === $data['survivant'] ? 'b' : 'a';
        [$survivante, $absorbee] = 'a' === $survivant ? [$ficheA, $ficheB] : [$ficheB, $ficheA];
        // Champs dont la valeur retenue est celle de l'absorbée : le choix
        // désigne un côté a/b, la copie ne concerne que l'autre fiche.
        $champsDepuisAbsorbee = [];
        foreach ($data as $nom => $choix) {
            if (str_starts_with($nom, FusionType::PREFIXE_CHAMP) && in_array($choix, ['a', 'b'], true) && $choix !== $survivant) {
                $champsDepuisAbsorbee[] = substr($nom, strlen(FusionType::PREFIXE_CHAMP));
            }
        }

        $acteur = $this->getUser();
        if (!$acteur instanceof User) {
            throw $this->createAccessDeniedException();
        }
        // L'audit trace la fusion comme telle sur les révisions des deux fiches.
        $request->attributes->set('_audit_action', 'merge');
        try {
            $fusionneur->fusionner($survivante, $absorbee, $champsDepuisAbsorbee, $acteur);
        } catch (\DomainException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $ecranComparaison;
        }

        $this->addFlash('success', sprintf(
            'Fusion appliquée : « %s » a absorbé « %s » (archivée). La fiche fusionnée repart en cours pour revalidation.',
            (string) $survivante->label(),
            (string) $absorbee->label(),
        ));

        return $this->redirectToRoute('app_mdm_referentiel_general', ['f' => $request->query->all('f')]);
    }
}
