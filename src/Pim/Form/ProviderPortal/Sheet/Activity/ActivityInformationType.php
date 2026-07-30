<?php

namespace App\Pim\Form\ProviderPortal\Sheet\Activity;

use App\Pim\Form\ProviderPortal\TagSelectType;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\ActivityInformationDTO;
use App\Pim\Model\ProviderPortal\Form\Tag\TagOption;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\AquaticTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\ArtisticTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\CulinaryTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\CulturalTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\DigitalTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\ExtremeTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\FunTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\LanguageChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\NaturalTagOptions;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\ThematicChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\TypeChoices;
use App\Pim\Model\ProviderPortal\Mock\Sheet\Activity\WellnessTagOptions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class ActivityInformationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('name', TextType::class, [
                'disabled' => true,
            ])
            ->add('types', ChoiceType::class, [
                'multiple' => true,
                'choices' => TypeChoices::getChoices(),
            ])
            ->add('languages', ChoiceType::class, [
                'multiple' => true,
                'choices' => LanguageChoices::getChoices(),
            ])
            ->add('thematic', ChoiceType::class, [
                'multiple' => true,
                'choices' => ThematicChoices::getChoices(),
            ])
        ;

        $this->addDependentThematicField('funList', 'sportives-ludiques', FunTagOptions::getTagOptions(), $builder);
        $this->addDependentThematicField('extremeList', 'sensations-fortes-sports-mecaniques', ExtremeTagOptions::getTagOptions(), $builder);
        $this->addDependentThematicField('aquaticList', 'nautiques-aquatiques', AquaticTagOptions::getTagOptions(), $builder);
        $this->addDependentThematicField('culinaryList', 'culinaires-oenologiques', CulinaryTagOptions::getTagOptions(), $builder);
        $this->addDependentThematicField('artisticList', 'creatives-artistiques-musicales', ArtisticTagOptions::getTagOptions(), $builder);
        $this->addDependentThematicField('culturalList', 'culturelles-reflexions-decouvertes', CulturalTagOptions::getTagOptions(), $builder);
        $this->addDependentThematicField('naturalList', 'nature-rse', NaturalTagOptions::getTagOptions(), $builder);
        $this->addDependentThematicField('wellnessList', 'bien-etre-detente', WellnessTagOptions::getTagOptions(), $builder);
        $this->addDependentThematicField('digitalList', 'digital', DigitalTagOptions::getTagOptions(), $builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ActivityInformationDTO::class,
            'label_format' => 'form.sheet.activity.information.%name%.label',
        ]);
    }

    /**
     * @param array<TagOption> $tagOptions
     */
    private function addDependentThematicField(string $fieldName, string $thematic, array $tagOptions, DynamicFormBuilder $builder): void
    {
        $builder->addDependent(
            $fieldName,
            'thematic',
            function (DependentField $field, ?array $values) use ($thematic, $tagOptions) {
                if (!$values || !in_array($thematic, $values, true)) {
                    return;
                }

                $field->add(TagSelectType::class, [
                    'tag_options' => $tagOptions,
                ]);
            },
        );
    }
}
