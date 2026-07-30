<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TagSelectType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('multiple', true);
        $resolver->setAllowedValues('multiple', [true]);
        $resolver->setRequired('tag_options');
        $resolver->setAllowedTypes('tag_options', sprintf('%s[]', TagOption::class));

        $resolver->setNormalizer('choices', fn (Options $options, $value) => $this->resolveChoices($options->offsetGet('tag_options')));
        $resolver->setNormalizer('choice_attr', fn (Options $options, $value) => $this->resolveChoiceAttributes($options->offsetGet('tag_options')));
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    /**
     * @param array<TagOption> $tagOptions
     *
     * @return array<string, string>
     */
    protected function resolveChoices(array $tagOptions): array
    {
        $choices = [];
        foreach ($tagOptions as $tagOption) {
            $choices[$tagOption->label] = $tagOption->value;
        }

        return $choices;
    }

    /**
     * @param array<TagOption> $tagOptions
     *
     * @return array<string, array{data-icon: string}>
     */
    protected function resolveChoiceAttributes(array $tagOptions): array
    {
        $choiceAttributes = [];

        foreach ($tagOptions as $tagOption) {
            $choiceAttributes[$tagOption->label] = ['data-choice-icon' => $tagOption->icon];
        }

        return $choiceAttributes;
    }
}
