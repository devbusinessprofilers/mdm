<?php

namespace App\Pim\Form\ProviderPortal\Auth;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\SubmitTypeAttributeEnum;
use App\Pim\Form\ProviderPortal\PasswordType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class CreatePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('password', RepeatedType::class, [
            'label' => false,
            'type' => PasswordType::class,
            'first_name' => 'password',
            'second_name' => 'confirmPassword',
            'first_options' => [
                'label' => 'form.new_password.label',
                'with_control' => true,
            ],
            'second_options' => [
                'label' => 'form.confirm_password.label',
            ],
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'global.login',
            'attr' => [
                SubmitTypeAttributeEnum::FULL->value => true,
            ],
        ]);
    }
}
