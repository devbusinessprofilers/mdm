<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Geo\ZonesGeographiques;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Sélecteur multiple d'une zone d'intervention mobile (pays, région ou
 * département, option `niveau`) sur le référentiel ZonesGeographiques. Les
 * valeurs historiques en texte libre sont résolues en codes à l'affichage
 * (les inconnues sont abandonnées) ; le modèle reste une liste de codes.
 *
 * @extends AbstractType<list<string>>
 */
final class ZoneGeoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $niveau = $options['niveau'];
        $builder->addModelTransformer(new CallbackTransformer(
            static function (?array $valeurs) use ($niveau): array {
                $codes = [];
                foreach ($valeurs ?? [] as $valeur) {
                    $code = match ($niveau) {
                        'pays' => ZonesGeographiques::resoudrePays((string) $valeur),
                        'region' => ZonesGeographiques::resoudreRegion((string) $valeur),
                        default => ZonesGeographiques::resoudreDepartement((string) $valeur),
                    };
                    if (null !== $code && !in_array($code, $codes, true)) {
                        $codes[] = $code;
                    }
                }

                return $codes;
            },
            static fn (?array $codes): array => array_values(array_filter(array_map(static fn (mixed $c): string => (string) $c, $codes ?? []), static fn (string $c): bool => '' !== $c)),
        ));
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // Lu par le contrôleur zones-geo sur le <select> natif (attributs
        // transmis au composant Select par le thème).
        $view->vars['attr'] = ['data-zones-geo-niveau' => $options['niveau']] + $view->vars['attr'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'niveau' => 'region',
            'multiple' => true,
            'required' => false,
            'choices' => static fn (\Symfony\Component\OptionsResolver\Options $options): array => match ($options['niveau']) {
                'pays' => ZonesGeographiques::pays(),
                'region' => ZonesGeographiques::regions(),
                default => ZonesGeographiques::departements(),
            },
        ]);
        $resolver->setAllowedValues('niveau', ['pays', 'region', 'departement']);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
