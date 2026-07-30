<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Account\Form\AffiliationType;
use App\Account\Form\CollaborateurInvitationType;
use App\Account\Service\FicheAffiliationManager;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\FicheCollaborateurRepository;
use App\Shared\Form\ActionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/collaborateurs', name: 'app_account_admin_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class CollaborateurAdminController extends AbstractController
{
    public function __construct(private readonly FormFactoryInterface $forms)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(FicheCollaborateurRepository $collaborateurs): Response
    {
        return $this->render('account/admin/index.html.twig', [
            'collaborateurs' => $collaborateurs->findBy([], ['email' => 'ASC']),
            'invitation_form' => $this->collaborateurInvitationForm()->createView(),
        ]);
    }

    #[Route('/inviter', name: 'create', methods: ['POST'])]
    public function create(Request $request, FicheAffiliationManager $manager): Response
    {
        $form = $this->collaborateurInvitationForm();
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
    public function show(FicheCollaborateur $collaborateur, FicheAffiliationRepository $affiliations): Response
    {
        $affiliationEntities = $affiliations->findBy(['collaborateur' => $collaborateur], ['createdAt' => 'DESC']);
        $editForms = [];
        $deleteForms = [];
        foreach ($affiliationEntities as $affiliation) {
            $editForms[$affiliation->idString()] = $this->affiliationEditForm($affiliation)->createView();
            $deleteForms[$affiliation->idString()] = $this->affiliationDeleteForm($affiliation)->createView();
        }

        return $this->render('account/admin/show.html.twig', [
            'collaborateur' => $collaborateur,
            'affiliations' => $affiliationEntities,
            'toggle_form' => $this->toggleForm($collaborateur)->createView(),
            'invite_form' => $this->affiliationInviteForm($collaborateur)->createView(),
            'edit_forms' => $editForms,
            'delete_forms' => $deleteForms,
        ]);
    }

    #[Route('/{id}/etat', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, FicheCollaborateur $collaborateur, EntityManagerInterface $entityManager): Response
    {
        $form = $this->toggleForm($collaborateur);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Formulaire invalide.');
        }
        $collaborateur->isActive() ? $collaborateur->deactivate() : $collaborateur->activate();
        $entityManager->flush();
        $this->addFlash('success', $collaborateur->isActive() ? 'Collaborateur activé.' : 'Collaborateur désactivé.');

        return $this->redirectToRoute('app_account_admin_show', ['id' => $collaborateur->id()]);
    }

    #[Route('/{id}/affiliations', name: 'invite', methods: ['POST'])]
    public function invite(Request $request, FicheCollaborateur $collaborateur, FicheAffiliationManager $manager): Response
    {
        $form = $this->affiliationInviteForm($collaborateur);
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
    public function editAffiliation(Request $request, FicheAffiliation $affiliation, FicheAffiliationManager $manager): Response
    {
        $form = $this->affiliationEditForm($affiliation);
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
    public function deleteAffiliation(Request $request, FicheAffiliation $affiliation, FicheAffiliationManager $manager): Response
    {
        $form = $this->affiliationDeleteForm($affiliation);
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

    /** @return FormInterface<mixed> */
    private function collaborateurInvitationForm(): FormInterface
    {
        return $this->forms->createNamed('invitation_collaborateur', CollaborateurInvitationType::class, null, [
            'action' => $this->generateUrl('app_account_admin_create'),
        ]);
    }

    /** @return FormInterface<mixed> */
    private function toggleForm(FicheCollaborateur $collaborateur): FormInterface
    {
        return $this->forms->createNamed('etat_collaborateur', ActionType::class, null, [
            'action' => $this->generateUrl('app_account_admin_toggle', ['id' => $collaborateur->id()]),
            'button_label' => $collaborateur->isActive() ? 'Désactiver' : 'Activer',
            'csrf_token_id' => 'toggle-collaborateur-'.$collaborateur->id(),
        ]);
    }

    /** @return FormInterface<mixed> */
    private function affiliationInviteForm(FicheCollaborateur $collaborateur): FormInterface
    {
        return $this->forms->createNamed('invitation_affiliation', AffiliationType::class, null, [
            'action' => $this->generateUrl('app_account_admin_invite', ['id' => $collaborateur->id()]),
            'with_fiche' => true,
            'button_label' => 'Ajouter',
            'csrf_token_id' => 'invite-affiliation-'.$collaborateur->id(),
        ]);
    }

    /** @return FormInterface<mixed> */
    private function affiliationEditForm(FicheAffiliation $affiliation): FormInterface
    {
        return $this->forms->createNamed('edition_affiliation_'.$affiliation->idString(), AffiliationType::class, [
            'role' => $affiliation->role(),
            'receivesRequests' => $affiliation->receivesRequests(),
        ], [
            'action' => $this->generateUrl('app_account_admin_affiliation_edit', ['id' => $affiliation->idString()]),
            'csrf_token_id' => 'edit-affiliation-'.$affiliation->idString(),
        ]);
    }

    /** @return FormInterface<mixed> */
    private function affiliationDeleteForm(FicheAffiliation $affiliation): FormInterface
    {
        return $this->forms->createNamed('suppression_affiliation_'.$affiliation->idString(), ActionType::class, null, [
            'action' => $this->generateUrl('app_account_admin_affiliation_delete', ['id' => $affiliation->idString()]),
            'button_label' => 'Retirer',
            'csrf_token_id' => 'delete-affiliation-'.$affiliation->idString(),
            'attr' => [
                'data-controller' => 'confirm',
                'data-confirm-message-value' => 'Retirer cette affiliation ?',
                'data-action' => 'submit->confirm#submit',
            ],
        ]);
    }
}
