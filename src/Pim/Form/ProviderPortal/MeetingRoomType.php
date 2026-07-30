<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Enum\ProviderPortal\Twig\Component\Form\Dropzone\AcceptedTypeEnum;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\Meeting\MeetingRoomDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MeetingRoomType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('area', NumberType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('theatreArea', NumberType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('meetingArea', NumberType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('uShapedArea', NumberType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('classArea', NumberType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('cabaretArea', NumberType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('banquetArea', NumberType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('cocktailArea', NumberType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('hasNaturalLight', CheckboxType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('hasAirConditioned', CheckboxType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('hasReducedMobilityAccess', CheckboxType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('hasDanceFloor', CheckboxType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('position', HiddenType::class)
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var MeetingRoomDTO|null $meetingRoom */
            $meetingRoom = $event->getData();

            $event->getForm()
                ->add('pictureFile', PictureFileType::class, [
                    'label' => false,
                    'required' => false,
                    'picture_url' => $meetingRoom?->pictureUrl,
                ])
                ->add('planFile', DocumentFileType::class, [
                    'label' => false,
                    'required' => false,
                    'accepted_type' => AcceptedTypeEnum::DOCUMENTS,
                    'file_name' => $meetingRoom?->getPlanFileName(),
                ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MeetingRoomDTO::class,
        ]);
    }
}
