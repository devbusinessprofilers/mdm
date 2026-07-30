<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Restaurant;

use App\Pim\Form\ProviderPortal\OptionalPriceType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant\RestaurantPricesDTO;
use App\Pim\Model\ProviderPortal\Form\OptionalPrice\InfosLine;
use App\Pim\Model\ProviderPortal\Form\OptionalPrice\PictoInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RestaurantPricesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('seatedLunch', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.restaurant.prices.seatedLunch',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('utensils', 'form.sheet.restaurant.prices.infos.catering_formula_all_inclusive')),
                ],
            ])
            ->add('cocktailLunchParty', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.restaurant.prices.cocktailLunchParty',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('building', 'form.sheet.restaurant.prices.infos.lunch_reception_space'))
                        ->addPictoInfo((new PictoInfo('cheers', 'form.sheet.restaurant.prices.infos.cocktail'))
                            ->setTransformer('form.sheet.restaurant.prices.infos.by_person'))
                        ->addPictoInfo((new PictoInfo('bottle', 'form.sheet.restaurant.prices.infos.wine'))
                            ->setTransformer('form.sheet.restaurant.prices.infos.by_three_persons')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.restaurant.prices.infos.minimum_participants')),
                ],
            ])
            ->add('seatedDinner', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.restaurant.prices.seatedDinner',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('utensils', 'form.sheet.restaurant.prices.infos.catering_formula_all_inclusive')),
                ],
            ])
            ->add('cocktailDinnerParty', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.restaurant.prices.cocktailDinnerParty',
                'collapse_content' => [
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('building', 'form.sheet.restaurant.prices.infos.lunch_reception_space'))
                        ->addPictoInfo((new PictoInfo('cheers', 'form.sheet.restaurant.prices.infos.cocktail'))
                            ->setTransformer('form.sheet.restaurant.prices.infos.by_person'))
                        ->addPictoInfo((new PictoInfo('bottle', 'form.sheet.restaurant.prices.infos.wine'))
                            ->setTransformer('form.sheet.restaurant.prices.infos.by_three_persons')),
                    (new InfosLine())
                        ->addPictoInfo(new PictoInfo('users', 'form.sheet.restaurant.prices.infos.minimum_participants')),
                ],
            ])
            ->add('wineOption', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.restaurant.prices.wineOption',
            ])
            ->add('alcoholOption', OptionalPriceType::class, [
                'required' => false,
                'switch_label' => 'form.sheet.restaurant.prices.alcoholOption',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RestaurantPricesDTO::class,
            'label_format' => 'form.sheet.restaurant.prices.%name%.label',
        ]);
    }
}
