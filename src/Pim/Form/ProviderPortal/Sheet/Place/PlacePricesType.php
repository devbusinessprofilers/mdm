<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Place;

use App\Pim\Form\ProviderPortal\CateringFormulaType;
use App\Pim\Form\ProviderPortal\CollectionType;
use App\Pim\Form\ProviderPortal\OptionalPriceType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlacePricesDTO;
use App\Pim\Model\ProviderPortal\Form\OptionalPrice\InfosLine;
use App\Pim\Model\ProviderPortal\Form\OptionalPrice\PictoInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlacePricesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('halfDayStudySeminar', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.half_day_study_seminar',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('cube', 'form.sheet.place.prices.infos.room'))
                        ->addPictoInfo((new PictoInfo('cookie_bite', 'form.sheet.place.prices.infos.collation'))
                            ->setTransformer('form.sheet.place.prices.infos.by_person'))
                        ->addPictoInfo((new PictoInfo('utensils', 'form.sheet.place.prices.infos.lunch'))
                            ->setTransformer('form.sheet.place.prices.infos.by_person')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])
            ->add('oneDayStudySeminar', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.one_day_study_seminar',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('cube', 'form.sheet.place.prices.infos.room'))
                        ->addPictoInfo((new PictoInfo('cookie_bite', 'form.sheet.place.prices.infos.two_collations'))
                            ->setTransformer('form.sheet.place.prices.infos.by_person'))
                        ->addPictoInfo((new PictoInfo('utensils', 'form.sheet.place.prices.infos.lunch'))
                            ->setTransformer('form.sheet.place.prices.infos.by_person')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])
            ->add('halfDayStudySeminarWithCocktail', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.half_day_study_seminar_with_cocktail',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('cube', 'form.sheet.place.prices.infos.room'))
                        ->addPictoInfo((new PictoInfo('cookie_bite', 'form.sheet.place.prices.infos.collation'))
                            ->setTransformer('form.sheet.place.prices.infos.by_person'))
                        ->addPictoInfo((new PictoInfo('utensils', 'form.sheet.place.prices.infos.lunch'))
                            ->setTransformer('form.sheet.place.prices.infos.by_person'))
                        ->addPictoInfo((new PictoInfo('cheers', 'form.sheet.place.prices.infos.cocktail'))
                            ->setTransformer('form.sheet.place.prices.infos.by_person'))
                        ->addPictoInfo((new PictoInfo('bottle', 'form.sheet.place.prices.infos.wine'))
                            ->setTransformer('form.sheet.place.prices.infos.by_three_persons')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])
            ->add('oneDayStudySeminarWithCocktail', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.one_day_study_seminar_with_cocktail',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('cube', 'form.sheet.place.prices.infos.room'))
                        ->addPictoInfo((new PictoInfo('cookie_bite', 'form.sheet.place.prices.infos.two_collations'))
                            ->setTransformer('form.sheet.place.prices.infos.by_person'))
                        ->addPictoInfo((new PictoInfo('utensils', 'form.sheet.place.prices.infos.lunch'))
                            ->setTransformer('form.sheet.place.prices.infos.by_person'))
                        ->addPictoInfo((new PictoInfo('cheers', 'form.sheet.place.prices.infos.cocktail'))
                            ->setTransformer('form.sheet.place.prices.infos.by_person'))
                        ->addPictoInfo((new PictoInfo('bottle', 'form.sheet.place.prices.infos.wine'))
                            ->setTransformer('form.sheet.place.prices.infos.by_three_persons')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])

            // Seminar with overnight stay
            ->add('semiResidentSeminar', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.semi_resident_seminar',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('cube', 'form.sheet.place.prices.infos.room'))
                        ->addPictoInfo(new PictoInfo('bed', 'form.sheet.place.prices.infos.accommodation'))
                        ->addPictoInfo(new PictoInfo('utensils', 'form.sheet.place.prices.infos.lunch_or_dinner')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])
            ->add('residentSeminar', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.resident_seminar',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('cube', 'form.sheet.place.prices.infos.room'))
                        ->addPictoInfo(new PictoInfo('bed', 'form.sheet.place.prices.infos.accommodation'))
                        ->addPictoInfo(new PictoInfo('utensils', 'form.sheet.place.prices.infos.lunch'))
                        ->addPictoInfo(new PictoInfo('utensils', 'form.sheet.place.prices.infos.dinner')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])
            ->add('allInclusiveSeminar', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.all_inclusive_seminar',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('cube', 'form.sheet.place.prices.infos.all_spaces'))
                        ->addPictoInfo(new PictoInfo('bed', 'form.sheet.place.prices.infos.accommodation'))
                        ->addPictoInfo(new PictoInfo('utensils', 'form.sheet.place.prices.infos.all_meals'))
                        ->addPictoInfo(new PictoInfo('bottle', 'form.sheet.place.prices.infos.all_sides')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])

            // Room-only location
            ->add('halfDayLocation', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.half_day_location',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('cube', 'form.sheet.place.prices.infos.room')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])
            ->add('oneDayLocation', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.one_day_location',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('cube', 'form.sheet.place.prices.infos.room')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])
            ->add('eveningLocation', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.evening_location',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('cube', 'form.sheet.place.prices.infos.room')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])

            // Cocktail and evening parties
            ->add('brunchCocktail', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.brunch_cocktail',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('building', 'form.sheet.place.prices.infos.lunch_reception_space'))
                        ->addPictoInfo((new PictoInfo('cheers', 'form.sheet.place.prices.infos.cocktail'))
                            ->setTransformer('form.sheet.place.prices.infos.by_person'))
                        ->addPictoInfo((new PictoInfo('bottle', 'form.sheet.place.prices.infos.wine'))
                            ->setTransformer('form.sheet.place.prices.infos.by_three_persons')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])
            ->add('cocktailParty', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.cocktail_party',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('building', 'form.sheet.place.prices.infos.evening_reception_space'))
                        ->addPictoInfo((new PictoInfo('cheers', 'form.sheet.place.prices.infos.cocktail'))
                            ->setTransformer('form.sheet.place.prices.infos.by_person'))
                        ->addPictoInfo((new PictoInfo('bottle', 'form.sheet.place.prices.infos.wine'))
                            ->setTransformer('form.sheet.place.prices.infos.by_three_persons')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])
            ->add('danceParty', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.dance_party',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('cube', 'form.sheet.place.prices.infos.room'))
                        ->addPictoInfo((new PictoInfo('cheers', 'form.sheet.place.prices.infos.cocktail'))
                            ->setTransformer('form.sheet.place.prices.infos.by_person'))
                        ->addPictoInfo((new PictoInfo('bottle', 'form.sheet.place.prices.infos.wine'))
                            ->setTransformer('form.sheet.place.prices.infos.by_three_persons'))
                        ->addPictoInfo(new PictoInfo('bottle', 'form.sheet.place.prices.infos.other_alcohol')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])
            ->add('dinnerParty', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.dinner_party',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('cube', 'form.sheet.place.prices.infos.room'))
                        ->addPictoInfo((new PictoInfo('chair', 'form.sheet.place.prices.infos.sit_down_dinner'))
                            ->setTransformer('form.sheet.place.prices.infos.transformer_all_inclusive')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])

            // Catering
            ->add('cateringFormulas', CollectionType::class, [
                'entry_type' => CateringFormulaType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'add_button_label' => 'form.catering_formula.add.label',
            ])
            ->add('sitDownLunch', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.sit_down_lunch',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('utensils', 'form.sheet.place.prices.infos.catering_formula_all_inclusive')),
                ],
            ])
            ->add('sitDownDinner', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.sit_down_dinner',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('utensils', 'form.sheet.place.prices.infos.catering_formula_all_inclusive')),
                ],
            ])
            ->add('wineOption', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.wine_option',
            ])
            ->add('alcoholOption', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.alcohol_option',
            ])

            // Group accommodation
            ->add('groupSingleRoom', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.group_single_room',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])
            ->add('groupTwinRoom', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.group_twin_room',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])
            ->add('groupDoubleRoom', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.place.prices.group_double_room',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.place.prices.infos.minimum_participants')),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlacePricesDTO::class,
            'label_format' => 'form.sheet.place.prices.%name%.label',
        ]);
    }
}
