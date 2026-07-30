<?php

namespace App\Pim\Form\ProviderPortal\Invoicing;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\LabelAttributeEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyVariantEnum;
use App\Pim\Form\ProviderPortal\NumberType;
use App\Pim\Model\ProviderPortal\DTO\Invoicing\CancellationDTO;
use App\Pim\Model\ProviderPortal\Form\Cancellation\GroupFrame;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CancellationType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $groups = [];
        $lastGroup = null;
        foreach ($form as $child) {
            $value = $child->getData();
            $group = $lastGroup;
            if (!$group || $group->value !== $value) {
                $group = new GroupFrame($value);
                $groups[] = $group;
            }

            $group->increment();
            $lastGroup = $group;
        }

        $view->vars['groups'] = $groups;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('frame1', NumberType::class, $this->numberTypeConfiguration())
            ->add('frame2', NumberType::class, $this->numberTypeConfiguration())
            ->add('frame3', NumberType::class, $this->numberTypeConfiguration())
            ->add('frame4', NumberType::class, $this->numberTypeConfiguration())
            ->add('frame5', NumberType::class, $this->numberTypeConfiguration())
            ->add('frame6', NumberType::class, $this->numberTypeConfiguration())
            ->add('frame7', NumberType::class, $this->numberTypeConfiguration())
            ->add('frame8', NumberType::class, $this->numberTypeConfiguration())
            ->add('frame9', NumberType::class, $this->numberTypeConfiguration())
            ->add('frame10', NumberType::class, $this->numberTypeConfiguration())
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CancellationDTO::class,
            'label_format' => 'form.invoicing.cancellation.%name%.label',
        ]);
    }

    /** @return array<string, mixed> */
    private function numberTypeConfiguration(): array
    {
        return [
            'label_attr' => [
                LabelAttributeEnum::BOLD->value => true,
                LabelAttributeEnum::TYPOGRAPHY->value => TypographyVariantEnum::BODY_SMALL->value,
            ],
            'required' => false,
            'min_value' => 0,
            'max_value' => 100,
        ];
    }
}
