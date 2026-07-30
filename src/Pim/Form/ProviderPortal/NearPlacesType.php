<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\ChoiceTypeAttributeEnum;
use App\Pim\Enum\ProviderPortal\Form\Twig\Attributes\TextTypeAttributeEnum;
use App\Pim\Enum\ProviderPortal\Localisation\NearPlaceTypeEnum;
use App\Pim\Form\DataTransformer\ProviderPortal\NearPlacesValueTransformer;
use App\Pim\Model\ProviderPortal\DTO\Localisation\CoordinatesDTO;
use App\Pim\Model\ProviderPortal\DTO\Localisation\NearPlacesDTO;
use App\Pim\Service\Localisation\ComputeRouteClientInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NearPlacesType extends AbstractType
{
    public function __construct(
        private readonly ComputeRouteClientInterface $computeRouteClient,
    ) {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['type'] = $options['type'];
        $view->vars['position'] = $options['position'];
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        parent::finishView($view, $form, $options);

        $labelFormat = $view->vars['label_format'] ?? null;
        $name = $view->vars['name'] ?? null;

        if ($labelFormat && $name && isset($view->children['placeChoices'])) {
            $view->children['placeChoices']->vars['label'] = str_replace(
                ['%name%', '%id%'],
                [$name, $view->vars['id']],
                $labelFormat
            );
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('placeChoices', ChoiceType::class, [
            ...$this->getPlaceChoicesOptions(),
            'choices' => [],
        ]);
        $builder->addModelTransformer(new NearPlacesValueTransformer());

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if (!isset($data['placeChoices'])) {
                return;
            }

            $choices = array_reduce($data['placeChoices'], function ($acc, $json) {
                $data = json_decode($json, true);
                $label = $data['label'] ?? null;
                $value = $data['value'] ?? null;
                if ($label && $value) {
                    $acc[$label] = $json;
                }

                return $acc;
            }, []);

            $form->add('placeChoices', ChoiceType::class, [
                ...$this->getPlaceChoicesOptions(),
                'choices' => $choices,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NearPlacesDTO::class,
        ]);

        $resolver->setRequired(['position']);
        $resolver->setAllowedTypes('position', CoordinatesDTO::class);

        $resolver->setRequired(['type']);
        $resolver->setAllowedTypes('type', NearPlaceTypeEnum::class);
    }

    /** @return array<string, mixed> */
    private function getPlaceChoicesOptions(): array
    {
        return [
            'required' => false,
            'multiple' => true,
            'attr' => [
                TextTypeAttributeEnum::PLACEHOLDER->value => 'global.placeholder.empty',
                ChoiceTypeAttributeEnum::TIP->value => 'global.near',
                ChoiceTypeAttributeEnum::TIP_ICON->value => 'paper-plane',
                ChoiceTypeAttributeEnum::LIMIT->value => 3,
                ChoiceTypeAttributeEnum::PROTOTYPE->value => true,
            ],
        ];
    }
}
