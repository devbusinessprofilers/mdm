<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Activite\OffreActivite;
use App\Pim\Enum\ModeTarificationActivite;
use App\Pim\Enum\TypeOffreActivite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<OffreActivite> */
final class OffreActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $field = static fn (string $label, string $get, string $set): array => [
            'label' => $label,
            'required' => false,
            'getter' => static fn (OffreActivite $o) => $o->{$get}(),
            'setter' => static function (OffreActivite &$o, mixed $v) use (
                $set,
            ): void {
                $o->{$set}($v);
            },
        ];
        // Le type est fixé par le bloc Forfaits / Options de l'éditeur (champ
        // caché) ; l'API PATCH continue d'envoyer 'forfait' / 'option'.
        $b->add(
            $b->create('type', HiddenType::class, $field('Type', 'type', 'changeType'))
                ->addModelTransformer(new CallbackTransformer(
                    static fn (?TypeOffreActivite $type): string => null === $type ? '' : $type->value,
                    static fn (?string $value): TypeOffreActivite => TypeOffreActivite::tryFrom((string) $value) ?? TypeOffreActivite::Forfait,
                )),
        )
            ->add('nom', TextType::class, $field('Nom', 'nom', 'changeNom'))
            ->add(
                'participantsMin',
                IntegerType::class,
                $field(
                    'Participants minimum',
                    'participantsMin',
                    'changeParticipantsMin',
                ),
            )
            ->add(
                'participantsMax',
                IntegerType::class,
                $field(
                    'Participants maximum',
                    'participantsMax',
                    'changeParticipantsMax',
                ),
            )
            ->add(
                'prix',
                MoneyType::class,
                $field('Prix', 'prix', 'changePrix') + [
                    'currency' => 'EUR',
                    'input' => 'string',
                ],
            )
            ->add(
                'modeTarification',
                ChoiceType::class,
                $field(
                    'Mode de tarification',
                    'modeTarification',
                    'changeModeTarification',
                ) + [
                    'choices' => [
                        'Par personne' => ModeTarificationActivite::ParPersonne,
                        'Forfait' => ModeTarificationActivite::Forfait,
                    ],
                    'choice_value' => static fn (
                        ?ModeTarificationActivite $mode,
                    ): ?string => $mode?->value,
                ],
            )
            // Position = n° d'emplacement du bloc Forfaits / Options (caché).
            ->add(
                $b->create('position', HiddenType::class, $field('Position', 'position', 'changePosition'))
                    ->addModelTransformer(new CallbackTransformer(
                        static fn (?int $position): string => (string) ($position ?? 0),
                        static fn (?string $value): ?int => null === $value || '' === trim($value) ? null : (int) $value,
                    )),
            );
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults(['data_class' => OffreActivite::class]);
    }
}
