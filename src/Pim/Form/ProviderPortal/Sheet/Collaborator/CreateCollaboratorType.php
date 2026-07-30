<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Collaborator;

use App\Pim\Model\ProviderPortal\DTO\Collaborator\CreateCollaboratorDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreateCollaboratorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', TextType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateCollaboratorDTO::class,
            'label_format' => 'form.sheet.collaborator.create.%name%.label',
        ]);
    }
}
