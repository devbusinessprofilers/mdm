<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Restaurant;

use App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant\RestaurantCsrDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Restaurant\CSRCommitmentChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RestaurantCsrType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('csrCommitments', ChoiceType::class, [
                'multiple' => true,
                'choices' => CSRCommitmentChoices::getChoices(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RestaurantCsrDTO::class,
            'label_format' => 'form.sheet.restaurant.csr.%name%.label',
        ]);
    }
}
