<?php

namespace App\Pim\Form\ProviderPortal\Auth;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\SubmitTypeAttributeEnum;
use App\Pim\Form\ProviderPortal\PasswordType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class EnterPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('password', PasswordType::class, [
            'label' => 'form.enter_password.label',
            'with_control' => false,
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'global.login',
            'attr' => [
                SubmitTypeAttributeEnum::FULL->value => true,
            ],
        ]);
    }
}
