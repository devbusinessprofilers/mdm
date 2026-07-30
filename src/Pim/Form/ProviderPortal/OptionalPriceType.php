<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\LabelAttributeEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyVariantEnum;
use App\Pim\Model\ProviderPortal\DTO\OptionalPriceDTO;
use App\Pim\Model\ProviderPortal\Form\OptionalPrice\InfosLine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class OptionalPriceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('isOptionSelected', SwitchButtonType::class, [
                'label' => $options['switch_label'],
                'label_attr' => [
                    LabelAttributeEnum::TYPOGRAPHY->value => TypographyVariantEnum::BODY_LARGE->value,
                    LabelAttributeEnum::BOLD->value => true,
                ],
            ])
        ;

        $builder->addDependent('price', 'isOptionSelected', $this->isOptionSelected(...));
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['collapseContent'] = $options['collapse_content'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OptionalPriceDTO::class,
            'label_format' => 'form.optional_price.%name%.label',
            'switch_label' => null,
            'collapse_content' => [],
        ]);

        $resolver->setAllowedTypes('switch_label', 'string');
        $resolver->setAllowedTypes('collapse_content', \sprintf('%s[]', InfosLine::class));

        $resolver->setRequired('switch_label');
    }

    private function isOptionSelected(DependentField $field, bool $isOptionSelected): void
    {
        if (!$isOptionSelected) {
            return;
        }

        $field->add(MoneyType::class, [
            'required' => false,
        ]);
    }
}
