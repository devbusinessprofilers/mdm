<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\NatureRessource;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/** @extends AbstractType<RessourceLieu> */
final class RestaurantRessourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('image', FileType::class, [
                'label' => 'Photo',
                'mapped' => false,
                'required' => false,
                'help' => 'PNG, JPG ou WEBP — 25 Mo maximum — 960 × 480 px minimum.',
                'constraints' => [
                    new Image(
                        maxSize: '25M',
                        mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
                        minWidth: 960,
                        minHeight: 480,
                        detectCorrupted: true,
                    ),
                ],
            ])
            ->add('usage', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Photo principale' => 'PHOTO_PRINCIPALE',
                    'Photo diverse' => 'PHOTO_DIVERSE',
                    'Photo de salle' => 'CONFIG_SALLE_PHOTO',
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
            ->add('restaurantSalle', EntityType::class, [
                'class' => RestaurantSalle::class,
                'choices' => $options['restaurant']->salles()->toArray(),
                'choice_label' => static fn (
                    RestaurantSalle $room,
                ): string => $room->nom(),
                'label' => 'Salle éventuelle',
                'required' => false,
                'getter' => static fn (
                    RessourceLieu $resource,
                ): ?RestaurantSalle => $resource->restaurantSalle(),
                'setter' => static function (
                    RessourceLieu &$resource,
                    ?RestaurantSalle $room,
                ): void {
                    $resource->changeRestaurantSalle($room);
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

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            static function (FormEvent $event): void {
                $resource = $event->getData();
                if (!$resource instanceof RessourceLieu) {
                    return;
                }

                $resource->changeNature(NatureRessource::Photo);
                $resource->changeSalle(null);
                $file = $event->getForm()->get('image')->getData();
                if (
                    '' === $resource->damAssetId()
                    && !$file instanceof UploadedFile
                ) {
                    $event->getForm()->get('image')->addError(
                        new FormError('Sélectionnez une photo.'),
                    );
                }
            },
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RessourceLieu::class]);
        $resolver->setRequired('restaurant');
        $resolver->setAllowedTypes('restaurant', Restaurant::class);
    }
}
