<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Enum\NatureRessource;
use App\Shared\Service\ParametreProviderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/** @extends AbstractType<RessourceLieu> */
final class RessourceLieuType extends AbstractType
{
    public function __construct(private readonly ParametreProviderInterface $parametres)
    {
    }

    /** @param FormBuilderInterface<RessourceLieu|null> $builder */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->field($builder, 'damAssetId', TextType::class, 'Identifiant DAM', [
            'required' => false,
            'empty_data' => '',
            'help' => 'Renseigné automatiquement après le téléversement.',
            'attr' => ['readonly' => true],
        ]);
        $maxMo = $this->parametres->int('dam.image_poids_max_mo');
        $minWidth = $this->parametres->int('dam.image_largeur_min');
        $minHeight = $this->parametres->int('dam.image_hauteur_min');
        $builder->add('image', FileType::class, [
            'label' => 'Image à téléverser',
            'mapped' => false,
            'required' => false,
            'help' => sprintf('PNG, JPG, JPEG ou WEBP — %d Mo maximum — %d × %d px minimum.', $maxMo, $minWidth, $minHeight),
            'constraints' => [new Image(
                maxSize: $maxMo.'M',
                mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
                minWidth: $minWidth,
                minHeight: $minHeight,
                detectCorrupted: true,
            )],
        ]);
        $this->field($builder, 'nature', ChoiceType::class, 'Nature', [
            'choices' => ['Photo' => NatureRessource::Photo, 'Document' => NatureRessource::Document, 'Vidéo' => NatureRessource::Video],
            'choice_value' => static fn (?NatureRessource $nature): ?string => $nature?->value,
        ]);
        $this->field($builder, 'usage', ChoiceType::class, 'Champ de la Bible', ['choices' => [
            'Façade' => 'PHOTO_FACADE',
            'Chambre' => 'PHOTO_CHAMBRE',
            'Restauration' => 'PHOTO_RESTAURATION',
            'Salle de réunion' => 'CONFIG_PHOTO_SALLE',
            'Divers' => 'PHOTO_DIVERSE',
            'Plan de salle' => 'CONFIG_PLAN_SALLE',
            'Photo de team building externe' => 'LOISIR_EXTERNE_PHOTO',
            'Photos du lieu' => 'PHOTO',
            'Plan général' => 'PJ_PLAN_GENERAL',
            'Supports commerciaux' => 'PJ_SUPPORT_COMMERCIAUX',
            'Attestation de vigilance URSSAF' => 'INFO_LEGALE_ATTESTATION_VIGILANCE_URSSAF',
            'Assurance RC professionnelle' => 'INFO_LEGALE_ASSURANCE_RC_PRO',
            'RIB' => 'MODE_PAIEMENT_RIB',
            "RIB d'affacturage" => 'AFFACTURAGE_RIB',
            'Conditions générales de vente' => 'COND_GEN_VENTE_DEPOT_DOC',
            'Fichier de convention' => 'CONV_PART_FICHIER',
            'Galerie (ancien code)' => 'galerie',
            'Plan général (ancien code)' => 'plan_general',
            'RSE (ancien code)' => 'rse',
            'Photo de salle (ancien code)' => 'photo_salle',
        ]]);
        $this->field($builder, 'salle', EntityType::class, 'Salle associée', [
            'class' => Salle::class,
            'choices' => $options['salle_choices'],
            'choice_label' => static fn (Salle $salle): string => $salle->nom() ?: 'Salle sans nom',
            'placeholder' => 'Aucune salle',
            'required' => false,
        ]);
        $this->field($builder, 'legende', TextType::class, 'Légende', ['required' => false]);
        $this->field($builder, 'source', TextType::class, 'Source / crédit', ['required' => false]);
        $this->field($builder, 'keywords', TextareaType::class, 'Mots-clés', ['required' => false]);
        $this->field($builder, 'rightsExpiresAt', DateType::class, 'Échéance des droits', ['required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable']);
        $this->field($builder, 'position', IntegerType::class, 'Position', ['required' => false]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $form = $event->getForm();
            $resource = $event->getData();
            if (!$resource instanceof RessourceLieu) {
                return;
            }
            $file = $form->get('image')->getData();
            if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $resource->changeNature(NatureRessource::Photo);
            }
            if ('' === $resource->damAssetId() && null === $file) {
                $form->get('image')->addError(new FormError('Sélectionnez une image à téléverser.'));
            }
            if ('CONFIG_PHOTO_SALLE' === $resource->usage() && null === $resource->salle()) {
                $form->get('salle')->addError(new FormError('Sélectionnez la salle associée à cette photo.'));
            }
            if (null !== $resource->salle() && 'CONFIG_PHOTO_SALLE' !== $resource->usage()) {
                $form->get('salle')->addError(new FormError('Une salle ne peut être associée qu’à la catégorie « Salle de réunion ».'));
            }
        });
    }

    /**
     * @param FormBuilderInterface<RessourceLieu|null> $builder
     * @param class-string<FormTypeInterface<mixed>> $type
     * @param array<string, mixed> $options
     */
    private function field(FormBuilderInterface $builder, string $name, string $type, string $label, array $options = []): void
    {
        $setter = 'change'.ucfirst($name);
        $builder->add($name, $type, $options + [
            'label' => $label,
            'getter' => static fn (RessourceLieu $ressource): mixed => $ressource->{$name}(),
            'setter' => static function (RessourceLieu &$ressource, mixed $value) use ($setter): void { $ressource->{$setter}($value); },
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RessourceLieu::class,
            'salle_choices' => [],
        ]);
        $resolver->setAllowedTypes('salle_choices', 'array');
    }
}
