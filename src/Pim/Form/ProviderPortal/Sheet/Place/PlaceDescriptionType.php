<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Place;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\TextTypeAttributeEnum;
use App\Pim\Form\ProviderPortal\WysiwygType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceDescriptionDTO;
use App\Pim\Service\Localisation\NearbyPlaceClientInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlaceDescriptionType extends AbstractType
{
    public function __construct(
        private readonly NearbyPlaceClientInterface $nearbyPlaceClient,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('global', WysiwygType::class)
            ->add('asset1', TextType::class, [
                'attr' => [
                    TextTypeAttributeEnum::MAX_LENGTH->value => 35,
                ],
            ])
            ->add('asset2', TextType::class, [
                'attr' => [
                    TextTypeAttributeEnum::MAX_LENGTH->value => 35,
                ],
            ])
            ->add('asset3', TextType::class, [
                'attr' => [
                    TextTypeAttributeEnum::MAX_LENGTH->value => 35,
                ],
            ])
            ->add('asset4', TextType::class, [
                'attr' => [
                    TextTypeAttributeEnum::MAX_LENGTH->value => 35,
                ],
            ])
            ->add('asset5', TextType::class, [
                'attr' => [
                    TextTypeAttributeEnum::MAX_LENGTH->value => 35,
                ],
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var PlaceDescriptionDTO|null $placeDescription */
            $placeDescription = $event->getData();

            if (null === $placeDescription?->coordinates) {
                return;
            }

            $pointOfInterests = $this->nearbyPlaceClient->nearbyPointsOfInterest($placeDescription->coordinates);

            $choices = [];
            foreach ($pointOfInterests as $pointOfInterest) {
                if (!$name = $pointOfInterest->displayName) {
                    continue;
                }

                $choices[$name] = $pointOfInterest->id;
            }

            $event->getForm()->add('significantPointIds', ChoiceType::class, [
                'multiple' => true,
                'required' => false,
                'choices' => $choices,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlaceDescriptionDTO::class,
            'label_format' => 'form.sheet.place.description.%name%.label',
        ]);
    }
}
