<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Activity;

use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\ActivityCsrDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\EsatProviderChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActivityCsrType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('esatProviders', ChoiceType::class, [
                'multiple' => true,
                'choices' => EsatProviderChoices::getChoices(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ActivityCsrDTO::class,
            'label_format' => 'form.sheet.activity.csr.%name%.label',
        ]);
    }
}
