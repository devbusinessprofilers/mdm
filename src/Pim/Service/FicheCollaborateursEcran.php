<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Account\Form\AffiliationType;
use App\Account\Form\CollaborateurInvitationType;
use App\Account\Message\CollaborateurAccessRequested;
use App\Account\Service\FicheAffiliationManager;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheAffiliation;
use App\Shared\Form\ActionType;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Gestion des collaborateurs depuis l'éditeur de fiche : invitation, édition
 * du rôle et des drapeaux, retrait. Toute la logique métier (dédoublonnage par
 * email, plafond des destinataires de demandes, verrous, autorisations) reste
 * dans FicheAffiliationManager — l'écran ne fait que le brancher.
 */
final readonly class FicheCollaborateursEcran
{
    public function __construct(
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private FicheAffiliationManager $manager,
        private OutboxPublisherInterface $outbox,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @return FormInterface<mixed> */
    public function formInvitation(Fiche $fiche): FormInterface
    {
        return $this->forms->createNamedBuilder('collab_invitation', options: [
            'action' => $this->urls->generate('app_mdm_fiche_collaborateur_inviter', ['id' => $fiche->idString()]),
            'csrf_token_id' => 'collab-invitation-'.$fiche->idString(),
        ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [new NotBlank(message: 'L\'email est obligatoire.'), new Email(message: 'L\'adresse email est invalide.')],
            ])
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'required' => false])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'required' => false])
            ->add('phone', TelType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('role', ChoiceType::class, CollaborateurInvitationType::roleOptions())
            ->add('receivesRequests', CheckboxType::class, [
                'label' => 'Traite les demandes',
                'required' => false,
            ])
            ->add('traiteContenus', CheckboxType::class, [
                'label' => 'Traite les contenus',
                'required' => false,
            ])
            ->add('traitePaiements', CheckboxType::class, [
                'label' => 'Traite les paiements',
                'required' => false,
            ])
            ->add('envoyerAcces', CheckboxType::class, [
                'label' => 'Envoyer les accès par email',
                'required' => false,
            ])
            ->add('inviter', SubmitType::class, ['label' => 'Inviter'])
            ->getForm();
    }

    /**
     * @param list<FicheAffiliation> $affiliations
     *
     * @return list<array{affiliation: FicheAffiliation, edition: FormInterface<mixed>, suppression: FormInterface<mixed>}>
     */
    public function formsAffiliations(Fiche $fiche, array $affiliations): array
    {
        $lignes = [];
        foreach ($affiliations as $affiliation) {
            $lignes[] = [
                'affiliation' => $affiliation,
                'edition' => $this->formEdition($affiliation),
                'suppression' => $this->forms->createNamed('collab_retrait_'.$affiliation->idString(), ActionType::class, null, [
                    'action' => $this->urls->generate('app_mdm_fiche_affiliation_retirer', ['id' => $affiliation->idString()]),
                    'button_label' => 'Retirer',
                    'csrf_token_id' => 'collab-retrait-'.$affiliation->idString(),
                    'attr' => [
                        'data-controller' => 'confirm',
                        'data-confirm-message-value' => 'Retirer cet utilisateur de la fiche ?',
                        'data-action' => 'submit->confirm#submit',
                    ],
                ]),
            ];
        }

        return $lignes;
    }

    /** @return FormInterface<mixed> */
    public function formEdition(FicheAffiliation $affiliation): FormInterface
    {
        $donnees = [
            'role' => $affiliation->role(),
            'receivesRequests' => $affiliation->receivesRequests(),
            'traiteContenus' => $affiliation->traiteContenus(),
            'traitePaiements' => $affiliation->traitePaiements(),
        ];
        if ($affiliation->collaborateur()->estInterne()) {
            $donnees['repli'] = $affiliation->repli();
        }
        $builder = $this->forms->createNamedBuilder('collab_edition_'.$affiliation->idString(), data: $donnees, options: [
            'action' => $this->urls->generate('app_mdm_fiche_affiliation_modifier', ['id' => $affiliation->idString()]),
            'csrf_token_id' => 'collab-edition-'.$affiliation->idString(),
        ])
            ->add('role', ChoiceType::class, CollaborateurInvitationType::roleOptions())
            ->add('receivesRequests', CheckboxType::class, ['label' => 'Traite les demandes', 'required' => false])
            ->add('traiteContenus', CheckboxType::class, ['label' => 'Traite les contenus', 'required' => false])
            ->add('traitePaiements', CheckboxType::class, ['label' => 'Traite les paiements', 'required' => false]);
        // Réservé au service référencement : la case n'apparaît que pour les
        // adresses internes, cf. AffiliationType::AIDE_REPLI.
        if ($affiliation->collaborateur()->estInterne()) {
            $builder->add('repli', CheckboxType::class, [
                'label' => 'Contact de repli', 'required' => false, 'help' => AffiliationType::AIDE_REPLI,
            ]);
        }

        return $builder->add('enregistrer', SubmitType::class, ['label' => 'Enregistrer'])->getForm();
    }

    /**
     * @param array{email: string, firstName: ?string, lastName: ?string, phone: ?string, role: FicheAffiliationRole, receivesRequests: bool, traiteContenus: bool, traitePaiements: bool, envoyerAcces: bool} $data
     *
     * @throws \DomainException quand le métier refuse (déjà affilié, plafond de destinataires…)
     */
    public function inviter(User $actor, Fiche $fiche, array $data): FicheAffiliation
    {
        $affiliation = $this->manager->invite(
            $actor,
            $fiche,
            $data['email'],
            $data['role'],
            $data['receivesRequests'],
            (string) $data['firstName'],
            (string) $data['lastName'],
            traiteContenus: $data['traiteContenus'],
            traitePaiements: $data['traitePaiements'],
        );
        if (null === $affiliation->collaborateur()->phone() && null !== $data['phone'] && '' !== trim($data['phone'])) {
            $affiliation->collaborateur()->changePhone($data['phone']);
        }
        if ($data['envoyerAcces']) {
            $this->outbox->enqueue(new CollaborateurAccessRequested(
                $affiliation->collaborateur()->id(),
                $fiche->idString(),
            ));
        }
        $this->entityManager->flush();

        return $affiliation;
    }

    /** @param array{role: FicheAffiliationRole, receivesRequests: bool, traiteContenus: bool, traitePaiements: bool, repli?: bool} $data */
    public function modifier(User $actor, FicheAffiliation $affiliation, array $data): void
    {
        $this->manager->update(
            $actor,
            $affiliation,
            $data['role'],
            $data['receivesRequests'],
            $data['traiteContenus'],
            $data['traitePaiements'],
            // Case absente pour les adresses non internes : ne pas toucher.
            $data['repli'] ?? null,
        );
    }

    public function retirer(User $actor, FicheAffiliation $affiliation): void
    {
        $this->manager->remove($actor, $affiliation);
    }
}
