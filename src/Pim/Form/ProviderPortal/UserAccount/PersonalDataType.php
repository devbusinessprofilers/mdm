<?php

namespace App\Pim\Form\ProviderPortal\UserAccount;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\TextTypeAttributeEnum;
use App\Pim\Form\ProviderPortal\AvatarType;
use App\Pim\Form\ProviderPortal\PasswordType;
use App\Pim\Model\ProviderPortal\DTO\UserDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PersonalDataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'form.firstName.label',
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'form.firstName.placeholder',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'form.lastName.label',
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'form.lastName.placeholder',
                ],
            ])
            ->add('email', TextType::class, [
                'label' => 'form.email.label',
                'required' => false,
                'disabled' => true,
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'form.email.placeholder',
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'form.phone.label',
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'form.phone.placeholder',
                ],
            ])
            ->add('job', TextType::class, [
                'label' => 'form.job.label',
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'form.job.placeholder',
                ],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'form.password.label',
                'required' => false,
                'disabled' => true,
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'form.password.placeholder',
                ],
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var UserDTO $user */
            $user = $event->getData();

            $event->getForm()->add('pictureFile', AvatarType::class, [
                'required' => false,
                'initials' => $user->getInitials(),
                'picture_url' => $user->pictureUrl,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserDTO::class,
        ]);
    }
}
