<?php

namespace App\Pim\Form\ProviderPortal\Sheet\MealTray;

use App\Pim\Form\ProviderPortal\ClosingPeriodType;
use App\Pim\Form\ProviderPortal\CollectionType;
use App\Pim\Form\ProviderPortal\OpeningHoursType;
use App\Pim\Form\ProviderPortal\PictureFileType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\MealTray\MealTrayInformationDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MealTrayInformationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('openingHours', OpeningHoursType::class, ['required' => false])
            ->add('closingPeriods', CollectionType::class, [
                'entry_type' => ClosingPeriodType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'add_button_label' => 'form.closing_periods.add.label',
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var MealTrayInformationDTO|null $mealTray */
            $mealTray = $event->getData();

            $event->getForm()
                ->add('pictureFile', PictureFileType::class, [
                    'required' => false,
                    'picture_url' => $mealTray?->pictureUrl,
                ])
                ->add('logoFile', PictureFileType::class, [
                    'required' => false,
                    'picture_url' => $mealTray?->logoUrl,
                ])
            ;
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MealTrayInformationDTO::class,
            'label_format' => 'form.sheet.mealTray.information.%name%.label',
        ]);
    }
}
