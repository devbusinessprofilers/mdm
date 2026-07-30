<?php

namespace App\Pim\Form\ProviderPortal\Invoicing;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\TextTypeAttributeEnum;
use App\Pim\Model\ProviderPortal\DTO\Invoicing\AddressDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('street', TextType::class)
            ->add('zipCode', TextType::class)
            ->add('city', TextType::class)
            ->add('country', ChoiceType::class, [
                'choices' => array_flip(Countries::getNames()),
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'global.placeholder.empty',
                ],
            ])
        ;

        if ($options['with_street2']) {
            $builder->add('street2', TextType::class, ['required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined('with_street2');
        $resolver->setAllowedTypes('with_street2', 'bool');

        $resolver->setDefaults([
            'data_class' => AddressDTO::class,
            'label_format' => 'form.invoicing.address.%name%.label',
            'with_street2' => false,
        ]);
    }
}
