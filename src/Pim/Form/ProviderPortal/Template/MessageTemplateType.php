<?php

namespace App\Pim\Form\ProviderPortal\Template;

use App\Pim\Form\ProviderPortal\WysiwygType;
use App\Pim\Model\ProviderPortal\DTO\Template\MessageTemplateDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MessageTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => false])
            ->add('content', WysiwygType::class, ['label' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MessageTemplateDTO::class,
        ]);
    }
}
