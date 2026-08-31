<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Localisation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Localisation> */
final class LocalisationType extends AbstractType
{
    /** @param FormBuilderInterface<Localisation|null> $builder */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ([
            'pays' => ['Pays', 'pays', 'changePays'],
            'countryCode' => ['Code pays ISO', 'countryCode', 'changeCountryCode'],
            'region' => ['Région', 'region', 'changeRegion'],
            'departement' => ['Département', 'departement', 'changeDepartement'],
            'ruePostale' => ['Rue', 'ruePostale', 'changeRuePostale'],
            'codePostal' => ['Code postal', 'codePostal', 'changeCodePostal'],
            'ville' => ['Ville', 'ville', 'changeVille'],
            'arrondissement' => ['Arrondissement', 'arrondissement', 'changeArrondissement'],
            'latitude' => ['Latitude', 'latitude', 'changeLatitude'],
            'longitude' => ['Longitude', 'longitude', 'changeLongitude'],
        ] as $name => [$label, $getter, $setter]) {
            $builder->add($name, TextType::class, [
                'label' => $label,
                'required' => false,
                'getter' => static fn (Localisation $localisation): mixed => $localisation->{$getter}(),
                'setter' => static function (Localisation &$localisation, mixed $value) use ($setter): void {
                    $localisation->{$setter}($value);
                },
            ]);

            if ('latitude' === $name || 'longitude' === $name) {
                $builder->get($name)->addModelTransformer(new CallbackTransformer(
                    static fn (?string $value): ?string => $value,
                    static function (?string $value) use ($name): ?string {
                        try {
                            return 'latitude' === $name
                                ? Localisation::normalizeLatitude($value)
                                : Localisation::normalizeLongitude($value);
                        } catch (\InvalidArgumentException $exception) {
                            throw new TransformationFailedException(
                                $exception->getMessage(),
                                0,
                                $exception,
                                'La coordonnée saisie est invalide.',
                            );
                        }
                    },
                ));
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Localisation::class,
        ]);
    }
}
