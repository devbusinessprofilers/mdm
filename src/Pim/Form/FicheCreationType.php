<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Account\Form\CollaborateurAutocompleteType;
use App\Pim\Enum\TypeFiche;
use App\Pim\Lov\ActiviteLovCatalog;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\Lov\RestaurantLovCatalog;
use App\Pim\Lov\ServiceLovCatalog;
use App\Pim\Repository\SiteDiffusionRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** @extends AbstractType<FicheCreation> */
final class FicheCreationType extends AbstractType
{
    private const GAMMES = [
        'Lieu' => TypeFiche::Lieu,
        'Restaurant' => TypeFiche::Restaurant,
        'Activité' => TypeFiche::Activite,
        'Service événementiel' => TypeFiche::ServiceEvenementiel,
    ];

    public function __construct(private readonly SiteDiffusionRepository $sitesDiffusion)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Gamme',
                'choices' => self::GAMMES,
                'choice_value' => static fn (?TypeFiche $type): ?string => $type?->value,
                'placeholder' => 'Choisir une gamme…',
                'constraints' => [new NotNull(message: 'Choisissez une gamme.')],
                'attr' => [
                    'data-fiche-creation-target' => 'type',
                    'data-action' => 'change->fiche-creation#refresh',
                ],
            ])
            ->add('label', TextType::class, [
                'label' => 'Nom de la fiche',
                'constraints' => [new NotBlank(message: 'Le nom de la fiche est obligatoire.')],
            ])
            ->add('localisation', LocalisationType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('modeIntervention', ChoiceType::class, [
                'label' => 'Adresse',
                'choices' => ['Adresse fixe' => 'fixe', 'Mobile' => 'mobile'],
                'placeholder' => 'Non précisé',
                'required' => false,
            ])
            ->add('lieuTypologie', ChoiceType::class, [
                'label' => 'Catégorie du lieu',
                'choices' => array_flip(LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE')),
                'multiple' => true,
                'required' => false,
            ])
            ->add('lieuThematique', ChoiceType::class, [
                'label' => 'Thématiques du lieu',
                'choices' => array_flip(LieuLovCatalog::choicesFor('TA_THEMATIQUE')),
                'multiple' => true,
                'required' => false,
            ])
            ->add('lieuAmbiance', ChoiceType::class, [
                'label' => 'Ambiances du lieu',
                'choices' => array_flip(LieuLovCatalog::choicesFor('TA_AMBIANCE')),
                'multiple' => true,
                'required' => false,
            ])
            ->add('typeRestaurant', ChoiceType::class, [
                'label' => 'Type de restaurant',
                'choices' => array_flip(RestaurantLovCatalog::values('TYPE_RESTAURANT')),
                'multiple' => true,
                'required' => false,
            ])
            ->add('thematiqueActivite', ChoiceType::class, [
                'label' => 'Type d’activité',
                'choices' => array_flip(ActiviteLovCatalog::choicesFor('THEMATIQUE_ACTIVITE')),
                'multiple' => true,
                'required' => false,
            ])
            ->add('typePrestataire', ChoiceType::class, [
                'label' => 'Type de prestataire',
                'choices' => array_flip(ServiceLovCatalog::prestations()),
                'multiple' => true,
                'required' => false,
            ])
            // Le niveau de statut (MICE_STATUT) ne se choisit que dans le
            // formulaire complet ; à la création, seul l'interrupteur existe.
            ->add('businessPremium', CheckboxType::class, [
                'label' => 'Adhérent Business Premium',
                'required' => false,
            ])
            ->add('sitePremium', ChoiceType::class, [
                'label' => 'Canaux de visibilité',
                'choices' => array_flip(LieuLovCatalog::choicesFor('SITE_PREMIUM')),
                'multiple' => true,
                'required' => false,
            ])
            ->add('sitesDiffusion', ChoiceType::class, [
                'label' => 'Sites de diffusion',
                'choices' => $this->sitesDiffusion->choicesGroupees(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'Les sites obligatoires sont réappliqués automatiquement.',
            ])
            ->add('contactRepli', CheckboxType::class, [
                'label' => 'Contact de repli',
                'required' => false,
                'help' => 'Le service référencement remplace le contact prestataire ; aucun accès n\'est envoyé.',
            ])
            ->add('collaborateurExistant', CollaborateurAutocompleteType::class, [
                'required' => false,
            ])
            ->add('collabPrenom', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
            ])
            ->add('collabNom', TextType::class, [
                'label' => 'Nom',
                'required' => false,
            ])
            ->add('collabEmail', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'constraints' => [new Email(message: 'L’adresse email est invalide.')],
            ])
            ->add('collabTelephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('envoyerAcces', CheckboxType::class, [
                'label' => 'Envoyer les accès par email',
                'required' => false,
            ])
            ->add('trameEmail', TextareaType::class, [
                'label' => 'Trame d’email',
                'required' => false,
                'help' => 'Texte de l’email d’accès envoyé au collaborateur.',
            ])
            ->add('creerQuandMeme', SubmitType::class, [
                'label' => 'Créer quand même',
                'attr' => ['class' => 'btn btn-secondary'],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Créer la fiche']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FicheCreation::class,
            'constraints' => [new Callback(self::validate(...))],
        ]);
    }

    public static function validate(mixed $data, ExecutionContextInterface $context): void
    {
        if (!$data instanceof FicheCreation) { return; }
        if ($data->envoyerAcces && $data->contactRepli) {
            $context->buildViolation('Un contact de repli ne reçoit pas d\'accès : décochez l\'envoi.')
                ->atPath('envoyerAcces')
                ->addViolation();
        }
        if ($data->envoyerAcces && !$data->contactRepli && !$data->veutCollaborateur()) {
            $context->buildViolation('Sélectionnez ou créez un collaborateur pour lui envoyer les accès.')
                ->atPath('collaborateurExistant')
                ->addViolation();
        }
        if (null === $data->collaborateurExistant
            && '' !== trim((string) $data->collabEmail)
            && '' === trim((string) $data->collabPrenom).trim((string) $data->collabNom)) {
            $context->buildViolation('Renseignez le prénom ou le nom du nouveau collaborateur.')
                ->atPath('collabPrenom')
                ->addViolation();
        }
    }
}
