<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Entity\User;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\FicheCreation;
use App\Pim\Form\FicheCreationType;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\ReadModel\FicheListResult;
use App\Pim\ReadModel\GlobalSearchItem;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\LocalisationRepository;
use App\Pim\Repository\SiteDiffusionRepository;
use App\Pim\Service\FicheCreationManager;
use App\Pim\Service\FicheDuplicateDetector;
use App\Pim\Service\FicheRouteResolver;
use App\Pim\Service\GeoapifyClient;
use App\Pim\Service\RechercheEntrepriseClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/referentiel/fiche', name: 'app_pim_fiche_')]
#[IsGranted('ROLE_BP_EDITOR')]
final class FicheController extends AbstractController
{
    // Route déclarée dans config/routes.yaml : /referentiel/fiche/nouvelle (app_mdm_creation_fiche).
    public function new(
        Request $request,
        FicheCreationManager $manager,
        FicheDuplicateDetector $duplicateDetector,
        FicheRouteResolver $routes,
        SiteDiffusionRepository $sitesDiffusion,
    ): Response {
        $creation = new FicheCreation();
        $creation->sitesDiffusion = $sitesDiffusion->idsPreselectionInitiale();
        // ?type=lieu : la gamme arrive pré-cochée (liens profonds du référentiel).
        $creation->type = TypeFiche::tryFrom($request->query->getString('type'));
        $form = $this->createForm(FicheCreationType::class, $creation);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw $this->createAccessDeniedException();
            }
            $entreprise = $manager->lookupEntreprise($creation);
            $duplicates = $duplicateDetector->detect($creation, $entreprise);
            $confirmButton = $form->get('creerQuandMeme');
            $confirmed = $confirmButton instanceof SubmitButton && $confirmButton->isClicked();
            if ([] !== $duplicates && !$confirmed) {
                // 422 : Turbo Drive ignore une réponse 200 à un POST de formulaire.
                return $this->render('pim/fiche/new.html.twig', [
                    'form' => $form->createView(),
                    'duplicates' => $duplicates,
                    'paysRecherche' => self::paysRecherche(),
                ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
            }
            try {
                $result = $manager->create($creation, $user, $entreprise);
                $this->addFlash('success', 'Fiche créée. Complétez maintenant les informations détaillées.');
                if (null !== $result->entreprise) {
                    $this->addFlash('success', sprintf(
                        'Fiche pré-remplie depuis l’annuaire des entreprises (%s%s).',
                        $result->entreprise->denomination ?? 'entreprise trouvée',
                        null !== $result->entreprise->siret ? ', SIRET '.$result->entreprise->siret : '',
                    ));
                } else {
                    $this->addFlash('warning', 'Aucune entreprise correspondante dans l’annuaire : la fiche a été créée sans pré-remplissage.');
                }

                return $this->redirect($routes->editUrl($result->fiche->type(), $result->fiche->idString()));
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render(
            'pim/fiche/new.html.twig',
            ['form' => $form->createView(), 'duplicates' => [], 'paysRecherche' => self::paysRecherche()],
            new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK),
        );
    }

    /**
     * Suggestions pour la recherche d'adresse du tunnel de création. En France,
     * l'annuaire des entreprises répond d'abord (lui seul connaît les raisons
     * sociales — « Business Profilers » n'existe ni dans la BAN ni dans OSM),
     * puis Geoapify complète avec des adresses ; ailleurs, Geoapify seul.
     * Les deux sources sont indépendantes : l'une en panne n'éteint pas l'autre.
     */
    #[Route('/adresse-autocomplete', name: 'adresse_autocomplete', methods: ['GET'])]
    public function adresseAutocomplete(
        Request $request,
        GeoapifyClient $geocodeur,
        RechercheEntrepriseClient $annuaire,
    ): Response {
        $nom = trim($request->query->getString('nom'));
        $q = trim($request->query->getString('q'));
        $pays = trim($request->query->getString('pays'));
        if (mb_strlen(trim($nom.' '.$q)) < 3 || 1 !== preg_match('/^[a-zA-Z]{2}$/', $pays)) {
            return $this->json(['suggestions' => []]);
        }
        $suggestions = [];
        if ('fr' === strtolower($pays)) {
            try {
                $suggestions = $annuaire->suggestionsAdresse(trim($nom.' '.$q));
            } catch (\RuntimeException) {
                // L'autocomplétion est un confort : API indisponible = pas de suggestion.
            }
        }
        try {
            $suggestions = array_merge($suggestions, $geocodeur->autocompleteFiche($nom, $q, $pays));
        } catch (\RuntimeException) {
        }

        return $this->json(['suggestions' => $suggestions]);
    }

    /**
     * Choix du sélecteur de pays de la recherche d'adresse (libellés français).
     *
     * @return list<array{value: string, label: string}>
     */
    private static function paysRecherche(): array
    {
        $choix = [];
        foreach (Countries::getNames('fr') as $code => $nom) {
            $choix[] = ['value' => $code, 'label' => $nom];
        }

        return $choix;
    }
}
