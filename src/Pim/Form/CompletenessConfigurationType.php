<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Enum\CompletenessFormula;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** @extends AbstractType<array<string, mixed>> */
final class CompletenessConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('formula', EnumType::class, [
            'label' => 'Formule', 'class' => CompletenessFormula::class,
            'choice_label' => static fn (CompletenessFormula $formula): string => $formula->label(),
        ])->add('weight', NumberType::class, [
            'label' => 'Poids', 'scale' => 2,
            'constraints' => [new GreaterThanOrEqual(0), new LessThanOrEqual(100000)],
        ])->add('targetLengthOverride', IntegerType::class, [
            'label' => 'Surcharge de longueur cible', 'required' => false,
            'help' => null === $options['default_target'] ? 'Aucune cible déclarée dans l’entité.' : sprintf('Laisser vide pour utiliser la cible de l’entité : %d caractères.', $options['default_target']),
            'constraints' => [new GreaterThanOrEqual(1)],
        ])->add('active', CheckboxType::class, ['label' => 'Règle active', 'required' => false])
            ->add('marketplace', CheckboxType::class, ['label' => 'Marketplace', 'required' => false])
            ->add('thematicSites', CheckboxType::class, ['label' => 'Sites thématiques', 'required' => false])
            ->add('salesforce', CheckboxType::class, ['label' => 'Salesforce', 'required' => false])
            ->add('providerPortal', CheckboxType::class, ['label' => 'Portail prestataire', 'required' => false])
            ->add('save', SubmitType::class, ['label' => 'Enregistrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'default_target' => null,
            'constraints' => [new Callback(static function (mixed $data, ExecutionContextInterface $context): void {
                if (!is_array($data) || CompletenessFormula::LengthRatio !== ($data['formula'] ?? null)) {
                    return;
                }
                $form = $context->getRoot();
                $default = $form->getConfig()->getOption('default_target');
                if (null === ($data['targetLengthOverride'] ?? null) && null === $default) {
                    $context->buildViolation('Une longueur cible est obligatoire pour cette formule.')->atPath('[targetLengthOverride]')->addViolation();
                }
                if (null !== $default && null !== ($data['targetLengthOverride'] ?? null) && (int) $data['targetLengthOverride'] > $default) {
                    $context->buildViolation(sprintf('La longueur cible ne peut pas dépasser la limite de l’entité (%d).', $default))->atPath('[targetLengthOverride]')->addViolation();
                }
            })],
        ]);
        $resolver->setAllowedTypes('default_target', ['null', 'int']);
    }
}
