<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Lov\LieuLovCatalog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Horaires d'ouverture par jour (maquette fiche Lieu) : un sous-formulaire par
 * valeur de la LOV DISPO_JOUR_OUVERTURE, chacun avec une heure d'ouverture et
 * une heure de fermeture (input natif `time`, valeurs « HH:MM »). Les données
 * voyagent en tableau — {jour: {ouverture, fermeture}} — vers la colonne JSON
 * de l'entité.
 *
 * @extends AbstractType<array<string, array{ouverture: ?string, fermeture: ?string}>|null>
 */
final class HorairesJoursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Champ texte natif `time` : la valeur voyage telle quelle en « HH:MM »
        // (le TimeType de Symfony exige un modèle H:i:s en entrée chaîne).
        $heure = static fn (string $libelle): array => [
            'label' => $libelle,
            'required' => false,
            'attr' => ['type' => 'time'],
            'constraints' => [new Regex('/^\d{2}:\d{2}$/', 'Heure attendue au format HH:MM.')],
        ];
        foreach (array_keys(LieuLovCatalog::choicesFor('DISPO_JOUR_OUVERTURE')) as $jour) {
            $builder->add(
                $builder->create((string) $jour, FormType::class, ['label' => false, 'required' => false])
                    ->add('ouverture', TextType::class, $heure('Ouverture'))
                    ->add('fermeture', TextType::class, $heure('Fermeture')),
            );
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['required' => false]);
    }
}
