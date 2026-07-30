<?php

namespace App\Pim\Form\ProviderPortal;

use App\Pim\Model\ProviderPortal\DTO\Localisation\CoordinatesDTO;
use App\Pim\Service\Map\MapPinFactory;
use App\Pim\Twig\Components\Form\LiveMap;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

class MapType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $data = $form->getData();
        $latitude = $data->latitude ?? null;
        $longitude = $data->longitude ?? null;
        $markers = [];

        if (count($options['markers']) > 0) {
            foreach ($options['markers'] as $marker) {
                $markers[] = $marker->toArray();
            }
        }

        if (!$data instanceof CoordinatesDTO || !$latitude || !$longitude) {
            $view->vars['center'] = $options['center'];
            $view->vars['zoom'] = $options['zoom'];
            $view->vars['markers'] = $markers;
            $view->vars['class'] = $options['class'];

            return;
        }

        $marker = (new Marker(
            new Point($latitude, $longitude),
            null,
            null,
            [],
            LiveMap::DEFAULT_TARGET_MARKER_ID,
            MapPinFactory::createHomePin()
        ))->toArray();

        $view->vars['center'] = [$latitude, $longitude];
        $view->vars['zoom'] = LiveMap::DEFAULT_TARGET_ZOOM;
        $view->vars['markers'] = [$marker, ...$markers];
        $view->vars['class'] = $options['class'];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('latitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('longitude', HiddenType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CoordinatesDTO::class,
            'label_format' => 'form.map.%name%.label',
            'zoom' => LiveMap::DEFAULT_ZOOM,
            'center' => [LiveMap::DEFAULT_LATITUDE, LiveMap::DEFAULT_LONGITUDE],
            'markers' => [],
            'class' => null,
        ]);

        $resolver->setAllowedTypes('zoom', 'int');
        $resolver->setAllowedTypes('center', ['float[]', 'null']);
        $resolver->setAllowedTypes('markers', \sprintf('%s[]', Marker::class));
        $resolver->setAllowedTypes('class', ['string', 'null']);
    }
}
