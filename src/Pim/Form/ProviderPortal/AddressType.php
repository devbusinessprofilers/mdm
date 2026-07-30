<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\TextTypeAttributeEnum;
use App\Pim\Model\ProviderPortal\DTO\Localisation\AddressDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('position', MapType::class)
            ->add('country', ChoiceType::class, [
                'choices' => array_flip(Countries::getNames()),
                'attr' => [
                    TextTypeAttributeEnum::PLACEHOLDER->value => 'global.placeholder.empty',
                ],
            ])
            ->add('city', SuggestionType::class)
            ->add('zipCode', SuggestionType::class)
            ->add('street', SuggestionType::class)
            ->add('district', SuggestionType::class, [
                'required' => false,
            ])
            ->add('department', SuggestionType::class, [
                'required' => false,
            ])
            ->add('area', SuggestionType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AddressDTO::class,
            'label_format' => 'form.address.%name%.label',
        ]);
    }
}
