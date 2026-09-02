<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\CritereGeo;
use App\Pim\Service\ReferentielGeographiqueFrancais;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Ligne de critère géographique d'un site de diffusion. La ville est choisie
 * dans les suggestions Geoapify (contrôleur critere-geo) qui remplissent les
 * coordonnées cachées : une saisie libre non choisie est refusée à la
 * validation — jamais de coordonnées saisies à la main.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class CritereGeoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Type de zone',
                'choices' => [
                    'Ville + rayon' => CritereGeo::TYPE_VILLE,
                    'Département' => CritereGeo::TYPE_DEPARTEMENT,
                    'Région' => CritereGeo::TYPE_REGION,
                    'Pays' => CritereGeo::TYPE_PAYS,
                ],
            ])
            ->add('villePays', ChoiceType::class, [
                'label' => 'Pays de la ville',
                'required' => false,
                'choices' => array_flip(Countries::getNames('fr')),
                'preferred_choices' => ['FR'],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville de référence',
                'required' => false,
                'help' => 'Choisissez la ville dans les suggestions : ses coordonnées sont géocodées automatiquement.',
            ])
            ->add('latitude', HiddenType::class, ['required' => false])
            ->add('longitude', HiddenType::class, ['required' => false])
            ->add('rayonKm', IntegerType::class, [
                'label' => 'Rayon (km)',
                'required' => false,
            ])
            ->add('departement', ChoiceType::class, [
                'label' => 'Département',
                'required' => false,
                'choices' => self::choix(ReferentielGeographiqueFrancais::departements()),
            ])
            ->add('region', ChoiceType::class, [
                'label' => 'Région',
                'required' => false,
                'choices' => self::choix(ReferentielGeographiqueFrancais::regions()),
            ])
            ->add('countryCode', ChoiceType::class, [
                'label' => 'Pays',
                'required' => false,
                'choices' => array_flip(Countries::getNames('fr')),
                'preferred_choices' => ['FR'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'constraints' => [new Callback(self::valider(...))],
        ]);
    }

    /** @param array<string, mixed>|null $critere */
    public static function valider(?array $critere, ExecutionContextInterface $context): void
    {
        $texte = static fn (string $cle): string => is_string($critere[$cle] ?? null) ? trim($critere[$cle]) : '';
        switch ($critere['type'] ?? null) {
            case CritereGeo::TYPE_VILLE:
                if ('' === $texte('ville') || '' === $texte('latitude') || '' === $texte('longitude')) {
                    $context->buildViolation('Choisissez une ville dans la liste de suggestions.')->atPath('[ville]')->addViolation();
                }
                if (!is_int($critere['rayonKm'] ?? null) || $critere['rayonKm'] < 1) {
                    $context->buildViolation('Indiquez un rayon d\'au moins un kilomètre.')->atPath('[rayonKm]')->addViolation();
                }
                break;
            case CritereGeo::TYPE_DEPARTEMENT:
                if ('' === $texte('departement')) {
                    $context->buildViolation('Choisissez un département.')->atPath('[departement]')->addViolation();
                }
                break;
            case CritereGeo::TYPE_REGION:
                if ('' === $texte('region')) {
                    $context->buildViolation('Choisissez une région.')->atPath('[region]')->addViolation();
                }
                break;
            case CritereGeo::TYPE_PAYS:
                if ('' === $texte('countryCode')) {
                    $context->buildViolation('Choisissez un pays.')->atPath('[countryCode]')->addViolation();
                }
                break;
            default:
                $context->buildViolation('Choisissez un type de zone.')->atPath('[type]')->addViolation();
        }
    }

    /**
     * @param list<string> $libelles
     *
     * @return array<string, string>
     */
    private static function choix(array $libelles): array
    {
        return array_combine($libelles, $libelles);
    }
}
