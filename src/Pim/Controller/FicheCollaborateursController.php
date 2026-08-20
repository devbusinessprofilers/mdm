<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Entity\User;
use App\Account\Security\FicheVoter;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\FicheCollaborateursEcran;
use App\Pim\Service\FicheEditeurEcran;
use App\Pim\Service\FicheSectionsCatalogue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Actions de la section Collaborateurs de l'éditeur de fiche : invitation,
 * édition et retrait d'affiliations, avec retour sur la section. Les règles
 * (dédoublonnage, plafond des destinataires, autorisations) restent portées
 * par FicheAffiliationManager.
 */
final class FicheCollaborateursController extends AbstractController
{
    #[
        Route(
            '/referentiel/fiche/{id}/collaborateurs/inviter',
            name: 'app_mdm_fiche_collaborateur_inviter',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function inviter(
        Request $request,
        string $id,
        FicheRepository $fiches,
        FicheCollaborateursEcran $ecran,
    ): Response {
        $fiche = $fiches->find($id);
        if (!$fiche instanceof Fiche) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::MANAGE_AFFILIATIONS, $fiche);
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $form = $ecran->formInvitation($fiche);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email: string, firstName: ?string, lastName: ?string, phone: ?string, role: \App\Account\Enum\FicheAffiliationRole, receivesRequests: bool, traiteContenus: bool, traitePaiements: bool, envoyerAcces: bool} $data */
            $data = $form->getData();
            try {
                $affiliation = $ecran->inviter($user, $fiche, $data);
                $this->addFlash('success', sprintf(
                    'Collaborateur « %s » affilié à la fiche%s.',
                    $affiliation->collaborateur()->email(),
                    $data['envoyerAcces'] ? ', les accès vont lui être envoyés' : '',
                ));
            } catch (\DomainException $exception) {
                $this->addFlash('warning', $exception->getMessage());
            }
        } else {
            $this->addFlash('warning', 'Invitation invalide : vérifiez l\'adresse email.');
        }

        if (TypeFiche::Traiteur === $fiche->type()) {
            return $this->redirectToRoute('app_mdm_referentiel_general');
        }
        $section = FicheSectionsCatalogue::indexBloc($fiche->type(), 'collaborateurs');

        return TypeFiche::Lieu === $fiche->type()
            ? $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $fiche->idString(), 'section' => $section])
            : $this->redirectToRoute('app_mdm_fiche_gamme', [
                'gamme' => FicheEditeurEcran::slug($fiche->type()),
                'id' => $fiche->idString(),
                'section' => $section,
            ]);
    }

    #[
        Route(
            '/referentiel/fiche-affiliation/{id}/modifier',
            name: 'app_mdm_fiche_affiliation_modifier',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function modifier(
        Request $request,
        string $id,
        FicheAffiliationRepository $affiliations,
        FicheCollaborateursEcran $ecran,
    ): Response {
        $affiliation = $affiliations->find($id);
        if (!$affiliation instanceof FicheAffiliation) {
            throw $this->createNotFoundException('Affiliation introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::MANAGE_AFFILIATIONS, $affiliation->fiche());
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $form = $ecran->formEdition($affiliation);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{role: \App\Account\Enum\FicheAffiliationRole, receivesRequests: bool, traiteContenus: bool, traitePaiements: bool, repli: bool} $data */
            $data = $form->getData();
            try {
                $ecran->modifier($user, $affiliation, $data);
                $this->addFlash('success', 'Affiliation mise à jour.');
            } catch (\DomainException $exception) {
                $this->addFlash('warning', $exception->getMessage());
            }
        }

        $fiche = $affiliation->fiche();

        if (TypeFiche::Traiteur === $fiche->type()) {
            return $this->redirectToRoute('app_mdm_referentiel_general');
        }
        $section = FicheSectionsCatalogue::indexBloc($fiche->type(), 'collaborateurs');

        return TypeFiche::Lieu === $fiche->type()
            ? $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $fiche->idString(), 'section' => $section])
            : $this->redirectToRoute('app_mdm_fiche_gamme', [
                'gamme' => FicheEditeurEcran::slug($fiche->type()),
                'id' => $fiche->idString(),
                'section' => $section,
            ]);
    }

    #[
        Route(
            '/referentiel/fiche-affiliation/{id}/retirer',
            name: 'app_mdm_fiche_affiliation_retirer',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function retirer(
        Request $request,
        string $id,
        FicheAffiliationRepository $affiliations,
        FicheCollaborateursEcran $ecran,
    ): Response {
        $affiliation = $affiliations->find($id);
        if (!$affiliation instanceof FicheAffiliation) {
            throw $this->createNotFoundException('Affiliation introuvable.');
        }
        $fiche = $affiliation->fiche();
        $this->denyAccessUnlessGranted(FicheVoter::MANAGE_AFFILIATIONS, $fiche);
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $form = $ecran->formsAffiliations($fiche, [$affiliation])[0]['suppression'];
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $ecran->retirer($user, $affiliation);
                $this->addFlash('success', 'Collaborateur retiré de la fiche.');
            } catch (\DomainException $exception) {
                $this->addFlash('warning', $exception->getMessage());
            }
        }

        if (TypeFiche::Traiteur === $fiche->type()) {
            return $this->redirectToRoute('app_mdm_referentiel_general');
        }
        $section = FicheSectionsCatalogue::indexBloc($fiche->type(), 'collaborateurs');

        return TypeFiche::Lieu === $fiche->type()
            ? $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $fiche->idString(), 'section' => $section])
            : $this->redirectToRoute('app_mdm_fiche_gamme', [
                'gamme' => FicheEditeurEcran::slug($fiche->type()),
                'id' => $fiche->idString(),
                'section' => $section,
            ]);
    }

}
