<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Service;

use App\Pim\Enum\ProviderPortal\Twig\Component\Form\Dropzone\AcceptedTypeEnum;
use App\Pim\Form\ProviderPortal\DropzoneType;
use App\Pim\Form\ProviderPortal\NumberType;
use App\Pim\Form\ProviderPortal\WysiwygType;
use App\Pim\Form\ProviderPortal\YesNoType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Service\ServiceInformationDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceInformationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'disabled' => true,
            ])
            ->add('description', WysiwygType::class)
            ->add('isEsat', YesNoType::class)
            ->add('isRse', YesNoType::class)
            ->add('pregnantSupport', YesNoType::class)
            ->add('deafSupport', YesNoType::class)
            ->add('blindSupport', YesNoType::class)
            ->add('withEquipment', YesNoType::class)
            ->add('userEquipmentRequired', YesNoType::class)
            ->add('receptionEquipmentRequired', YesNoType::class)
            ->add('withConstraint', YesNoType::class)
            ->add('minCapacity', NumberType::class)
            ->add('maxCapacity', NumberType::class)
            ->add('duration', NumberType::class)
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var ServiceInformationDTO|null $serviceInformation */
            $serviceInformation = $event->getData();

            $event->getForm()
                ->add('pictureFiles', DropzoneType::class, [
                    'required' => false,
                    'accepted_type' => AcceptedTypeEnum::IMAGES,
                    'max_file_count' => 2,
                    'documents' => $serviceInformation?->pictureDocuments,
                ])
                ->add('videoFiles', DropzoneType::class, [
                    'required' => false,
                    'accepted_type' => AcceptedTypeEnum::VIDEOS,
                    'max_file_count' => 2,
                    'documents' => $serviceInformation?->videoDocuments,
                ])
            ;
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServiceInformationDTO::class,
            'label_format' => 'form.sheet.service.information.%name%.label',
        ]);
    }
}
