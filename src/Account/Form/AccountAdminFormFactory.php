<?php

declare(strict_types=1);

namespace App\Account\Form;

use App\Pim\Entity\FicheAffiliation;
use App\Pim\Entity\FicheCollaborateur;
use App\Account\Entity\User;
use App\Shared\Form\ActionType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class AccountAdminFormFactory
{
    public const ROLES = ['Éditeur' => 'ROLE_BP_EDITOR', 'Validateur' => 'ROLE_BP_VALIDATOR'];

    public function __construct(private FormFactoryInterface $forms, private UrlGeneratorInterface $urls) {}

    /** @return FormInterface<mixed> */
    public function collaborateurInvitation(): FormInterface
    {
        return $this->forms->createNamed('invitation_collaborateur', CollaborateurInvitationType::class, null, ['action' => $this->urls->generate('app_account_admin_create')]);
    }

    /** @return FormInterface<mixed> */
    public function collaborateurToggle(FicheCollaborateur $collaborateur): FormInterface
    {
        return $this->forms->createNamed('etat_collaborateur', ActionType::class, null, [
            'action' => $this->urls->generate('app_account_admin_toggle', ['id' => $collaborateur->id()]),
            'button_label' => $collaborateur->isActive() ? 'Désactiver' : 'Activer', 'csrf_token_id' => 'toggle-collaborateur-'.$collaborateur->id(),
        ]);
    }

    /** @return FormInterface<mixed> */
    public function affiliationInvite(FicheCollaborateur $collaborateur): FormInterface
    {
        return $this->forms->createNamed('invitation_affiliation', AffiliationType::class, null, [
            'action' => $this->urls->generate('app_account_admin_invite', ['id' => $collaborateur->id()]), 'with_fiche' => true,
            'button_label' => 'Ajouter', 'csrf_token_id' => 'invite-affiliation-'.$collaborateur->id(),
        ]);
    }

    /** @return FormInterface<mixed> */
    public function affiliationEdit(FicheAffiliation $affiliation): FormInterface
    {
        return $this->forms->createNamed('edition_affiliation_'.$affiliation->idString(), AffiliationType::class, [
            'role' => $affiliation->role(), 'receivesRequests' => $affiliation->receivesRequests(),
        ], ['action' => $this->urls->generate('app_account_admin_affiliation_edit', ['id' => $affiliation->idString()]), 'csrf_token_id' => 'edit-affiliation-'.$affiliation->idString()]);
    }

    /** @return FormInterface<mixed> */
    public function affiliationDelete(FicheAffiliation $affiliation): FormInterface
    {
        return $this->forms->createNamed('suppression_affiliation_'.$affiliation->idString(), ActionType::class, null, [
            'action' => $this->urls->generate('app_account_admin_affiliation_delete', ['id' => $affiliation->idString()]),
            'button_label' => 'Retirer', 'csrf_token_id' => 'delete-affiliation-'.$affiliation->idString(),
            'attr' => ['data-controller' => 'confirm', 'data-confirm-message-value' => 'Retirer cette affiliation ?', 'data-action' => 'submit->confirm#submit'],
        ]);
    }

    /** @return FormInterface<mixed> */
    public function internalInvite(): FormInterface
    {
        return $this->forms->createNamedBuilder('form')->setAction($this->urls->generate('app_account_user_admin_invite'))
            ->add('email', EmailType::class)->add('role', ChoiceType::class, ['choices' => self::ROLES])
            ->add('submit', SubmitType::class, ['label' => 'Inviter'])->getForm();
    }

    /** @return FormInterface<mixed> */
    public function internalRole(User $user): FormInterface
    {
        $current = in_array('ROLE_BP_VALIDATOR', $user->getRoles(), true) ? 'ROLE_BP_VALIDATOR' : 'ROLE_BP_EDITOR';
        return $this->forms->createNamedBuilder('role_'.$user->id(), data: ['role' => $current])
            ->setAction($this->urls->generate('app_account_user_admin_role', ['id' => $user->id()]))
            ->add('role', ChoiceType::class, ['choices' => self::ROLES])->add('submit', SubmitType::class, ['label' => 'Changer'])->getForm();
    }

    /** @return FormInterface<mixed> */
    public function internalToggle(User $user, string $label): FormInterface
    {
        return $this->forms->createNamed('action', ActionType::class, null, [
            'action' => $this->urls->generate('app_account_user_admin_toggle', ['id' => $user->id()]), 'button_label' => $label,
            'csrf_token_id' => 'user-toggle-'.$user->id(),
        ]);
    }

    /** @return FormInterface<mixed> */
    public function internalResendCredentials(User $user): FormInterface
    {
        $label = null === $user->getPassword() ? 'Renvoyer l’invitation' : 'Envoyer un lien de reset';

        return $this->forms->createNamed('renvoi_identifiants_'.$user->id(), ActionType::class, null, [
            'action' => $this->urls->generate('app_account_user_admin_resend_credentials', ['id' => $user->id()]),
            'button_label' => $label,
            'csrf_token_id' => 'user-resend-credentials-'.$user->id(),
        ]);
    }

    /** @return FormInterface<mixed> */
    public function internalDelete(User $user): FormInterface
    {
        return $this->forms->createNamed('suppression_utilisateur_'.$user->id(), ActionType::class, null, [
            'action' => $this->urls->generate('app_account_user_admin_delete', ['id' => $user->id()]),
            'button_label' => 'Supprimer',
            'csrf_token_id' => 'user-delete-'.$user->id(),
            'attr' => [
                'data-controller' => 'confirm',
                'data-confirm-message-value' => 'Supprimer et anonymiser définitivement cet utilisateur ?',
                'data-action' => 'submit->confirm#submit',
            ],
        ]);
    }
}
