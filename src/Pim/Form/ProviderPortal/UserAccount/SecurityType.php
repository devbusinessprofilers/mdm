<?php

namespace App\Pim\Form\ProviderPortal\UserAccount;

use App\Pim\Form\ProviderPortal\PasswordType;
use App\Pim\Model\ProviderPortal\DTO\SecurityDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SecurityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('password', PasswordType::class, [
                'label' => 'form.password.label',
            ])
            ->add('newPassword', RepeatedType::class, [
                'label' => false,
                'type' => PasswordType::class,
                'first_name' => 'newPassword',
                'second_name' => 'confirmPassword',
                'first_options' => [
                    'label' => 'form.new_password.label',
                    'with_control' => true,
                ],
                'second_options' => [
                    'label' => 'form.confirm_password.label',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SecurityDTO::class,
        ]);
    }
}
