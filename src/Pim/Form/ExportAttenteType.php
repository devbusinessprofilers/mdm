<?php

declare(strict_types=1);

namespace App\Pim\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Vue d'attente de l'export Excel : la case « téléchargement automatique »
 * n'est jamais soumise au serveur, elle pilote le contrôleur Stimulus
 * `export-attente` côté client.
 *
 * @extends AbstractType<array{auto: bool}>
 */
final class ExportAttenteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('auto', CheckboxType::class, [
            'label' => false,
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => false,
        ]);
    }
}
