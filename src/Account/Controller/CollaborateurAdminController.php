<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Account\Form\AccountAdminFormFactory;
use App\Account\Service\FicheAffiliationManager;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\FicheCollaborateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/collaborateurs', name: 'app_account_admin_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class CollaborateurAdminController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(FicheCollaborateurRepository $collaborateurs, AccountAdminFormFactory $forms): Response
    {
        return $this->render('account/admin/index.html.twig', [
            'collaborateurs' => $collaborateurs->findBy([], ['email' => 'ASC']),
            'invitation_form' => $forms->collaborateurInvitation()->createView(),
        ]);
    }

    #[Route('/inviter', name: 'create', methods: ['POST'])]
    public function create(Request $request, FicheAffiliationManager $manager, AccountAdminFormFactory $forms): Response
    {
        $form = $forms->collaborateurInvitation();
        $form->handleRequest($request);
        $actor = $this->getUser();
        if (!$actor instanceof User || !$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Invitation invalide.');

            return $this->redirectToRoute('app_account_admin_index');
        }
        /** @var array{email: string, firstName?: string|null, lastName?: string|null, language: string, fiche: Fiche, role: FicheAffiliationRole, receivesRequests?: bool} $data */
        $data = $form->getData();
        try {
            $affiliation = $manager->invite(
                $actor, $data['fiche'], $data['email'], $data['role'],
                $data['receivesRequests'] ?? false,
                $data['firstName'] ?? '', $data['lastName'] ?? '', $data['language'],
            );
            $this->addFlash('success', 'Collaborateur invité et affilié.');

            return $this->redirectToRoute('app_account_admin_show', ['id' => $affiliation->collaborateur()->id()]);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_account_admin_index');
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(FicheCollaborateur $collaborateur, FicheAffiliationRepository $affiliations, AccountAdminFormFactory $forms): Response
    {
        $affiliationEntities = $affiliations->findBy(['collaborateur' => $collaborateur], ['createdAt' => 'DESC']);
        $editForms = [];
        $deleteForms = [];
        foreach ($affiliationEntities as $affiliation) {
            $editForms[$affiliation->idString()] = $forms->affiliationEdit($affiliation)->createView();
            $deleteForms[$affiliation->idString()] = $forms->affiliationDelete($affiliation)->createView();
        }

        return $this->render('account/admin/show.html.twig', [
            'collaborateur' => $collaborateur,
            'affiliations' => $affiliationEntities,
            'toggle_form' => $forms->collaborateurToggle($collaborateur)->createView(),
            'invite_form' => $forms->affiliationInvite($collaborateur)->createView(),
            'edit_forms' => $editForms,
            'delete_forms' => $deleteForms,
        ]);
    }

    #[Route('/{id}/etat', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, FicheCollaborateur $collaborateur, FicheAffiliationManager $manager, AccountAdminFormFactory $forms): Response
    {
        $form = $forms->collaborateurToggle($collaborateur);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Formulaire invalide.');
        }
        $manager->toggleCollaborateur($collaborateur);
        $this->addFlash('success', $collaborateur->isActive() ? 'Collaborateur activé.' : 'Collaborateur désactivé.');

        return $this->redirectToRoute('app_account_admin_show', ['id' => $collaborateur->id()]);
    }

    #[Route('/{id}/affiliations', name: 'invite', methods: ['POST'])]
    public function invite(Request $request, FicheCollaborateur $collaborateur, FicheAffiliationManager $manager, AccountAdminFormFactory $forms): Response
    {
        $form = $forms->affiliationInvite($collaborateur);
        $form->handleRequest($request);
        $actor = $this->getUser();
        if (!$actor instanceof User || !$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Formulaire invalide.');
        }
        /** @var array{fiche: Fiche, role: FicheAffiliationRole, receivesRequests?: bool} $data */
        $data = $form->getData();
        try {
            $manager->invite($actor, $data['fiche'], $collaborateur->email(), $data['role'], $data['receivesRequests'] ?? false);
            $this->addFlash('success', 'Affiliation ajoutée.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_account_admin_show', ['id' => $collaborateur->id()]);
    }

    #[Route('/affiliations/{id}/modifier', name: 'affiliation_edit', methods: ['POST'])]
    public function editAffiliation(Request $request, FicheAffiliation $affiliation, FicheAffiliationManager $manager, AccountAdminFormFactory $forms): Response
    {
        $form = $forms->affiliationEdit($affiliation);
        $form->handleRequest($request);
        $actor = $this->getUser();
        if (!$actor instanceof User || !$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Rôle invalide.');
        }
        /** @var array{role: FicheAffiliationRole, receivesRequests?: bool} $data */
        $data = $form->getData();
        try {
            $manager->update($actor, $affiliation, $data['role'], $data['receivesRequests'] ?? false);
            $this->addFlash('success', 'Affiliation modifiée.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_account_admin_show', ['id' => $affiliation->collaborateur()->id()]);
    }

    #[Route('/affiliations/{id}/supprimer', name: 'affiliation_delete', methods: ['POST'])]
    public function deleteAffiliation(Request $request, FicheAffiliation $affiliation, FicheAffiliationManager $manager, AccountAdminFormFactory $forms): Response
    {
        $form = $forms->affiliationDelete($affiliation);
        $form->handleRequest($request);
        $actor = $this->getUser();
        if (!$actor instanceof User || !$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException();
        }
        $collaborateurId = $affiliation->collaborateur()->id();
        $manager->remove($actor, $affiliation);
        $this->addFlash('success', 'Affiliation retirée.');

        return $this->redirectToRoute('app_account_admin_show', ['id' => $collaborateurId]);
    }

}
