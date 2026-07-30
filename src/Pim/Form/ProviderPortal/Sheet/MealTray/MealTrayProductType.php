<?php

namespace App\Pim\Form\ProviderPortal\Sheet\MealTray;

use App\Pim\Enum\ProviderPortal\Twig\Component\Form\Dropzone\AcceptedTypeEnum;
use App\Pim\Form\ProviderPortal\DropzoneType;
use App\Pim\Form\ProviderPortal\NumberType;
use App\Pim\Form\ProviderPortal\Sheet\VatPriceType;
use App\Pim\Form\ProviderPortal\TagSelectType;
use App\Pim\Form\ProviderPortal\WysiwygType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\MealTray\MealTrayProductDTO;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\AllergenTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\DelayLimitChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\DietaryPreferenceTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\DishTemperatureChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\MealTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\OrderLimitChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\TimeLimitChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\TypeChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MealTrayProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('type', ChoiceType::class, [
                'choices' => TypeChoices::getChoices(),
            ])
            ->add('shortDescription', WysiwygType::class)
            ->add('longDescription', WysiwygType::class)
            ->add('dishTemperature', ChoiceType::class, [
                'expanded' => true,
                'choices' => DishTemperatureChoices::getChoices(),
            ])
            ->add('vatPrice', VatPriceType::class, ['label' => false])
            ->add('capacity', NumberType::class)
            ->add('minOrderCount', NumberType::class)
            ->add('maxOrderCount', NumberType::class)
            ->add('dietaryPreferences', TagSelectType::class, [
                'required' => false,
                'tag_options' => DietaryPreferenceTagOptions::getTagOptions(),
            ])
            ->add('allergens', TagSelectType::class, [
                'required' => false,
                'tag_options' => AllergenTagOptions::getTagOptions(),
            ])
            ->add('meals', TagSelectType::class, [
                'required' => false,
                'tag_options' => MealTagOptions::getTagOptions(),
            ])
            ->add('orderLimit', ChoiceType::class, [
                'choices' => OrderLimitChoices::getChoices(),
            ])
            ->add('timeLimit', ChoiceType::class, [
                'choices' => TimeLimitChoices::getChoices(),
            ])
            ->add('delayLimit', ChoiceType::class, [
                'choices' => DelayLimitChoices::getChoices(),
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var MealTrayProductDTO $mealTrayProduct */
            $mealTrayProduct = $event->getData();

            $event->getForm()
                ->add('pictureFiles', DropzoneType::class, [
                    'accepted_type' => AcceptedTypeEnum::IMAGES,
                    'max_file_count' => 2,
                    'documents' => $mealTrayProduct?->pictureDocuments,
                ])
            ;
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MealTrayProductDTO::class,
            'label_format' => 'form.sheet.mealTray.product.%name%.label',
        ]);
    }
}
