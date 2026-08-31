<?php

declare(strict_types=1);

namespace App\Pim\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de l'écran de fusion de deux fiches : choix de la fiche
 * survivante, un choix a/b par champ divergent (présélectionné sur la valeur
 * la plus récente) et les versions des deux fiches en verrou optimiste.
 * Les champs divergents sont dérivés des deux fiches par FusionEcran, la
 * soumission reconstruit donc exactement le même formulaire.
 */
/** @extends AbstractType<mixed> */
final class FusionType extends AbstractType
{
    /** Préfixe des champs de choix, pour ne pas entrer en collision avec survivant/version_*. */
    public const PREFIXE_CHAMP = 'champ_';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('survivant', ChoiceType::class, [
                'label' => false,
                'multiple' => false,
                'expanded' => true,
                'choices' => ['a' => 'a', 'b' => 'b'],
                'data' => $options['survivant_defaut'],
            ])
            ->add('version_a', HiddenType::class, ['data' => (string) $options['version_a']])
            ->add('version_b', HiddenType::class, ['data' => (string) $options['version_b']]);

        /** @var list<array{nom: string, preselection: string}> $champs */
        $champs = $options['champs'];
        foreach ($champs as $champ) {
            $builder->add(self::PREFIXE_CHAMP.$champ['nom'], ChoiceType::class, [
                'label' => false,
                'multiple' => false,
                'expanded' => true,
                'choices' => ['a' => 'a', 'b' => 'b'],
                'data' => $champ['preselection'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'method' => 'POST',
            'csrf_token_id' => 'referentiel-fusion',
            'champs' => [],
            'survivant_defaut' => 'a',
            'version_a' => 0,
            'version_b' => 0,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'fusion';
    }
}
