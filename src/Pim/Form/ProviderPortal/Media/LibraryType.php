<?php

namespace App\Pim\Form\ProviderPortal\Media;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\TextTypeAttributeEnum;
use App\Pim\Model\ProviderPortal\DTO\Media\LibraryDTO;
use App\Pim\Model\ProviderPortal\Form\Media\LibraryConfiguration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LibraryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var LibraryConfiguration $configuration */
        $configuration = $options['library_configuration'];

        $builder
            ->add('pictures', MediaCollectionType::class, [
                'for_picture' => true,
                'maxFileCount' => $configuration->pictureMaxFileCount,
                'fileMaxSize' => $configuration->pictureFileMaxSize,
                'imageMinWidth' => $configuration->pictureMinWidth,
                'imageMinHeight' => $configuration->pictureMinHeight,
                'entry_options' => [
                    'categories' => $configuration->pictureCategories,
                    'meeting_room_trigger_value' => $configuration->meetingRoomTriggerValue,
                ],
            ])
            ->add('plans', MediaCollectionType::class, [
                'for_picture' => false,
                'maxFileCount' => $configuration->planMaxFileCount,
                'fileMaxSize' => $configuration->planFileMaxSize,
            ])
            ->add('documents', MediaCollectionType::class, [
                'for_picture' => false,
                'maxFileCount' => $configuration->documentMaxFileCount,
                'fileMaxSize' => $configuration->documentFileMaxSize,
            ])
            ->add('videoLink', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'form.sheet.library.videoLink.placeholder',
                ],
            ])
            ->add('optIn', CheckboxType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['library_configuration']);
        $resolver->setAllowedTypes('library_configuration', LibraryConfiguration::class);

        $resolver->setDefaults([
            'data_class' => LibraryDTO::class,
            'label_format' => 'form.sheet.library.%name%.label',
        ]);
    }
}
