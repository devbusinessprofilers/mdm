<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Enum\ProviderPortal\TranslatableEnumInterface;
use App\Pim\Form\DataTransformer\ProviderPortal\EnumTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @template T of \BackedEnum
 *
 * @template-extends AbstractType<T>
 */
class EnumType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new EnumTransformer(
            $options['enum'],
            $options['use_name_as_value'],
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('enum')
            ->setDefined(['label_prefix', 'use_name_as_label', 'use_name_as_value'])
            ->setAllowedValues('label_prefix', ['null', 'string'])
            ->setAllowedTypes('enum', 'string')
            ->setAllowedValues('enum', function ($value) {
                /** @var class-string<\BackedEnum> $value */
                return enum_exists($value);
            })
            ->setDefaults([
                'use_name_as_label' => false,
                'use_name_as_value' => false,
            ])
        ;

        $resolver->setNormalizer('choices', function (Options $options, $value) {
            if (!empty($value)) {
                return $value;
            }

            /** @var class-string<\BackedEnum> $class */
            $class = $options['enum'];
            $choices = [];

            /** @var \BackedEnum $enum */
            foreach ($class::cases() as $enum) {
                $choiceValue = (string) $options['use_name_as_value'] ? $enum->name : $enum->value;
                $choiceKey = (string) $options['use_name_as_label'] ? $enum->name : $enum->value;

                if (isset($options['label_prefix']) && is_string($options['label_prefix'])) {
                    $choiceKey = sprintf('%s.%s', $options['label_prefix'], $choiceKey);
                } elseif ($enum instanceof TranslatableEnumInterface) {
                    $choiceKey = $enum->getTranslationKey();
                }

                $choices[$choiceKey] = $choiceValue;
            }

            return $choices;
        });
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
