<?php

declare(strict_types=1);

namespace App\Pim\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Enregistrement de la vue courante de la liste : un nom sur les filtres en
 * cours, personnelle par défaut, partagée à l'équipe sur demande.
 *
 * @extends AbstractType<array{name: ?string, shared: bool}>
 */
final class SavedViewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la vue',
                'constraints' => [
                    new NotBlank(message: 'Le nom de la vue est obligatoire.'),
                    new Length(max: 120),
                ],
            ])
            ->add('shared', CheckboxType::class, [
                'label' => 'Partager avec l\'équipe',
                'required' => false,
            ])
            ->add('enregistrer', SubmitType::class, ['label' => 'Enregistrer la vue']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'method' => 'POST',
            'csrf_token_id' => 'referentiel-vue',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'vue';
    }
}
