<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Shared\Service\ParametreProviderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/** @extends AbstractType<RessourceLieu> */
final class ActiviteRessourceType extends AbstractType
{
    public function __construct(private readonly ParametreProviderInterface $parametres)
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $maxMo = $this->parametres->int('dam.image_poids_max_mo');
        $minWidth = $this->parametres->int('dam.image_largeur_min');
        $minHeight = $this->parametres->int('dam.image_hauteur_min');
        $builder
            ->add('image', FileType::class, [
                'label' => 'Photo',
                'mapped' => false,
                'required' => false,
                'help' => sprintf('PNG, JPG ou WEBP — %d Mo maximum — %d × %d px minimum.', $maxMo, $minWidth, $minHeight),
                'constraints' => [
                    new Image(
                        maxSize: $maxMo.'M',
                        mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
                        minWidth: $minWidth,
                        minHeight: $minHeight,
                        detectCorrupted: true,
                    ),
                ],
            ])
            ->add('usage', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Photo diverse' => 'PHOTO_DIVERSE',
                ],
                'getter' => static fn (
                    RessourceLieu $resource,
                ): string => $resource->usage(),
                'setter' => static function (
                    RessourceLieu &$resource,
                    string $value,
                ): void {
                    $resource->changeUsage($value);
                },
            ])
            ->add('legende', TextType::class, [
                'label' => 'Légende',
                'required' => false,
                'getter' => static fn (
                    RessourceLieu $resource,
                ): ?string => $resource->legende(),
                'setter' => static function (
                    RessourceLieu &$resource,
                    ?string $value,
                ): void {
                    $resource->changeLegende($value);
                },
            ])
            ->add('source', TextType::class, [
                'label' => 'Source / crédit', 'required' => false,
                'getter' => static fn (RessourceLieu $resource): ?string => $resource->source(),
                'setter' => static function (RessourceLieu &$resource, ?string $value): void { $resource->changeSource($value); },
            ])
            ->add('keywords', TextareaType::class, [
                'label' => 'Mots-clés', 'required' => false,
                'getter' => static fn (RessourceLieu $resource): ?string => $resource->keywords(),
                'setter' => static function (RessourceLieu &$resource, ?string $value): void { $resource->changeKeywords($value); },
            ])
            ->add('rightsExpiresAt', DateType::class, [
                'label' => 'Échéance des droits', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable',
                'getter' => static fn (RessourceLieu $resource): ?\DateTimeImmutable => $resource->rightsExpiresAt(),
                'setter' => static function (RessourceLieu &$resource, ?\DateTimeImmutable $value): void { $resource->changeRightsExpiresAt($value); },
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Position',
                'required' => false,
                'getter' => static fn (
                    RessourceLieu $resource,
                ): int => $resource->position(),
                'setter' => static function (
                    RessourceLieu &$resource,
                    ?int $value,
                ): void {
                    $resource->changePosition($value);
                },
            ]);
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (
            FormEvent $event,
        ): void {
            $resource = $event->getData();
            if (!$resource instanceof RessourceLieu) {
                return;
            }
            $resource->changeNature(NatureRessource::Photo);
            $resource->changeSalle(null);
            $file = $event->getForm()->get('image')->getData();
            if (
                '' === $resource->damAssetId()
                && !($file instanceof UploadedFile)
            ) {
                $event
                    ->getForm()
                    ->get('image')
                    ->addError(new FormError('Sélectionnez une photo.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RessourceLieu::class]);
    }
}
