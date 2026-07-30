<?php

namespace App\Pim\Form\ProviderPortal\Auth;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\SubmitTypeAttributeEnum;
use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\TextTypeAttributeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ForgotPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', TextType::class, [
            'label' => 'form.forgot_password.label',
            'required' => true,
            'attr' => [
                TextTypeAttributeEnum::PLACEHOLDER->value => 'form.forgot_password.placeholder',
            ],
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'form.forgot_password.submit',
            'attr' => [
                SubmitTypeAttributeEnum::FULL->value => true,
            ],
        ]);
    }
}
