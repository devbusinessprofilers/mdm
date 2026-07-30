<?php

namespace App\Pim\Form\ProviderPortal\Media;

use App\Pim\Model\ProviderPortal\DTO\Media\PictureDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceMeetingDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PictureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', HiddenType::class)
            ->add('crop', HiddenType::class)
            ->add('rank', HiddenType::class)
        ;

        $builder->get('crop')
            ->addModelTransformer(new CallbackTransformer(
                function ($cropData): ?string {
                    if (!is_array($cropData) || empty($cropData)) {
                        return null;
                    }

                    return json_encode($cropData);
                },
                function ($encodedCrop): array {
                    if (!is_string($encodedCrop) || empty($encodedCrop)) {
                        return [];
                    }

                    return json_decode($encodedCrop, true);
                }
            ))
        ;

        if (!empty($options['categories'])) {
            $categoryAttributes = [];
            if (isset($options['meeting_room_trigger_value'])) {
                $categoryAttributes['data-meeting-room-trigger'] = $options['meeting_room_trigger_value'];
            }

            $builder->add('category', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => $options['categories'],
                'attr' => $categoryAttributes,
            ]);

            if (!empty($options['meeting_room_trigger_value'])) {
                $builder->add('meetingRoom', ChoiceType::class, [
                    'label' => false,
                    'choices' => PlaceMeetingDTO::mock()->meetingRooms,
                    'choice_value' => 'name',
                    'choice_label' => 'name',
                ]);
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined(['categories', 'meeting_room_trigger_value']);

        $resolver->setAllowedTypes('categories', ['string[]', 'null']);
        $resolver->setAllowedTypes('meeting_room_trigger_value', ['string', 'null']);

        $resolver->setDefaults([
            'data_class' => PictureDTO::class,
            'label' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'media_picture';
    }
}
