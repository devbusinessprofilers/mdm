<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Service;

use App\Pim\Model\ProviderPortal\DTO\Sheet\Service\ServiceTypologyDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\ActivityChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\CommunicationChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\DigitalChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\FacilityChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\FoodChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\GiftChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\MarketingChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\MiscellaneousChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\ReceptionChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\TranslationChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Service\TransportChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceTypologyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('receptionList', ChoiceType::class, [
                'multiple' => true,
                'choices' => ReceptionChoices::getChoices(),
            ])
            ->add('giftList', ChoiceType::class, [
                'multiple' => true,
                'choices' => GiftChoices::getChoices(),
            ])
            ->add('communicationList', ChoiceType::class, [
                'multiple' => true,
                'choices' => CommunicationChoices::getChoices(),
            ])
            ->add('facilityList', ChoiceType::class, [
                'multiple' => true,
                'choices' => FacilityChoices::getChoices(),
            ])
            ->add('digitalList', ChoiceType::class, [
                'multiple' => true,
                'choices' => DigitalChoices::getChoices(),
            ])
            ->add('activityList', ChoiceType::class, [
                'multiple' => true,
                'choices' => ActivityChoices::getChoices(),
            ])
            ->add('translationList', ChoiceType::class, [
                'multiple' => true,
                'choices' => TranslationChoices::getChoices(),
            ])
            ->add('transportList', ChoiceType::class, [
                'multiple' => true,
                'choices' => TransportChoices::getChoices(),
            ])
            ->add('foodList', ChoiceType::class, [
                'multiple' => true,
                'choices' => FoodChoices::getChoices(),
            ])
            ->add('miscellaneousList', ChoiceType::class, [
                'multiple' => true,
                'choices' => MiscellaneousChoices::getChoices(),
            ])
            ->add('marketingList', ChoiceType::class, [
                'multiple' => true,
                'choices' => MarketingChoices::getChoices(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServiceTypologyDTO::class,
            'label_format' => 'form.sheet.service.typology.%name%.label',
        ]);
    }
}
