<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Place;

use App\Pim\Form\ProviderPortal\PictureFileType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\TeamBuildingDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TeamBuildingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contractor', TextType::class, ['required' => false])
            ->add('activity', TextType::class, ['required' => false])
            ->add('pictureFile', PictureFileType::class, [
                'label' => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TeamBuildingDTO::class,
            'label_format' => 'form.sheet.place.leisure.teamBuilding.%name%.label',
        ]);
    }
}
