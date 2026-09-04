<?php

declare(strict_types=1);

namespace App\Pim\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Durée saisie en « hh:mm » (input natif `time`, comme les horaires par
 * jour) et stockée en minutes entières : l'affichage suit la maquette
 * portail, la colonne ne change pas. Le champ natif plafonne à 23:59.
 *
 * @extends AbstractType<int|null>
 */
final class DureeMinutesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?int $minutes): string => null === $minutes ? '' : sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60),
            static function (?string $value): ?int {
                $value = trim((string) $value);
                if ('' === $value) {
                    return null;
                }
                if (1 !== preg_match('/^(\d{1,3}):(\d{2})$/', $value, $m) || (int) $m[2] > 59) {
                    throw new TransformationFailedException('Durée attendue au format hh:mm.');
                }

                return (int) $m[1] * 60 + (int) $m[2];
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'attr' => ['type' => 'time'],
            'invalid_message' => 'Durée attendue au format hh:mm.',
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
