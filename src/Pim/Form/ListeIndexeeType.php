<?php

declare(strict_types=1);

namespace App\Pim\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Liste de N textes courts saisis dans N champs séparés (« Les plus / atouts
 * 1 » à 5 de la maquette), stockée comme une liste JSON : les lignes vides
 * disparaissent. Les enfants se nomment 0..N-1, donc une liste envoyée telle
 * quelle par l'API PATCH (`["a", "b"]`) reste acceptée — les emplacements
 * absents sont alors vidés (la liste est remplacée, pas fusionnée).
 *
 * @extends AbstractType<list<string>>
 */
final class ListeIndexeeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $nombre = $options['nombre'];
        for ($i = 0; $i < $nombre; ++$i) {
            $builder->add((string) $i, TextType::class, [
                'label' => sprintf($options['libelle_format'], $i + 1),
                'required' => false,
                'attr' => $options['entry_attr'],
            ]);
        }

        $builder->addModelTransformer(new CallbackTransformer(
            static function (?array $values) use ($nombre): array {
                $values = array_values($values ?? []);
                $slots = [];
                for ($i = 0; $i < $nombre; ++$i) {
                    $slots[$i] = isset($values[$i]) ? (string) $values[$i] : null;
                }

                return $slots;
            },
            static fn (?array $slots): array => array_values(array_filter(array_map(
                static fn (mixed $item): string => trim((string) ($item ?? '')),
                $slots ?? [],
            ), static fn (string $item): bool => '' !== $item)),
        ));

        // Une liste soumise (API) ne nomme que ses emplacements remplis : les
        // autres sont soumis vides pour que la liste soit bien remplacée.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($nombre): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }
            for ($i = 0; $i < $nombre; ++$i) {
                if (!array_key_exists($i, $data) && !array_key_exists((string) $i, $data)) {
                    $data[$i] = null;
                }
            }
            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'nombre' => 5,
            'libelle_format' => '%d',
            'entry_attr' => [],
            'compound' => true,
            'data_class' => null,
            'required' => false,
        ]);
        $resolver->setAllowedTypes('nombre', 'int');
        $resolver->setAllowedTypes('libelle_format', 'string');
        $resolver->setAllowedTypes('entry_attr', 'array');
    }
}
