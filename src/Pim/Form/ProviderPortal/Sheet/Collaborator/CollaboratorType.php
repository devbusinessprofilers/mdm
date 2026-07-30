<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Collaborator;

use App\Pim\Form\ProviderPortal\AvatarType;
use App\Pim\Form\ProviderPortal\CollectionType;
use App\Pim\Model\ProviderPortal\DTO\Collaborator\CollaboratorDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CollaboratorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class)
            ->add('lastName', TextType::class)
            ->add('email', TextType::class, ['disabled' => true])
            ->add('phone', TextType::class)
            ->add('pictureFile', AvatarType::class, [
                'inverted' => $options['for_desktop'],
            ])
        ;

        if ($options['with_memberships']) {
            $builder->add('memberships', CollectionType::class, [
                'entry_type' => MembershipType::class,
                'entry_options' => [
                    'label' => false,
                    'mode' => MembershipType::MODE_COLLABORATOR,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined(['with_memberships', 'for_desktop']);
        $resolver->setAllowedTypes('with_memberships', 'bool');
        $resolver->setAllowedTypes('for_desktop', 'bool');

        $resolver->setDefaults([
            'data_class' => CollaboratorDTO::class,
            'label_format' => 'form.sheet.collaborator.%name%.label',
            'with_memberships' => false,
            'for_desktop' => true,
        ]);
    }
}
