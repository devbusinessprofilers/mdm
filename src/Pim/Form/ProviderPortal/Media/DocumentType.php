<?php

namespace App\Pim\Form\ProviderPortal\Media;

use App\Pim\Model\ProviderPortal\DTO\Media\DocumentDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', HiddenType::class)
            ->add('rank', HiddenType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentDTO::class,
            'label' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'media_document';
    }
}
