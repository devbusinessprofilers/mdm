<?php

namespace App\Pim\Form\ProviderPortal\Template;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyVariantEnum;
use App\Pim\Form\ProviderPortal\CollectionType;
use App\Pim\Model\ProviderPortal\DTO\Template\TemplateListDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TemplateListType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('messageTemplates', CollectionType::class, [
                'entry_type' => MessageTemplateType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'label_typography_variant' => TypographyVariantEnum::HEADING_3,
                'add_button_label' => 'form.templateList.messageTemplates.add.label',
                'information_text' => 'form.templateList.messageTemplates.description',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TemplateListDTO::class,
            'label_format' => 'form.templateList.%name%.label',
        ]);
    }
}
