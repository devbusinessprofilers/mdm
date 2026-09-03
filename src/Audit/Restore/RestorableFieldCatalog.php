<?php

declare(strict_types=1);

namespace App\Audit\Restore;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\LieuAdministratif;
use App\Pim\Entity\Lieu\LieuTarification;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Périmètre V1 : uniquement les champs scalaires (scalaires, enums, dates,
 * tableaux de scalaires) portés par la fiche, son entité de détail et ses
 * sous-entités singulières. Les chemins à crochets (collections, médias,
 * LOV) et le statut workflow ne sont pas rejouables par setter.
 */
final readonly class RestorableFieldCatalog
{
    private const PREFIX_CLASSES = [
        'fiche' => Fiche::class,
        'lieu' => Lieu::class,
        'activite' => Activite::class,
        'service' => ServiceEvenementiel::class,
        'restaurant' => Restaurant::class,
        'localisation' => Localisation::class,
        'administratif' => LieuAdministratif::class,
        'tarification' => LieuTarification::class,
    ];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function isRestorable(string $path): bool
    {
        return null !== $this->setterParameter($path);
    }

    public function restore(Fiche $fiche, string $path, mixed $value): void
    {
        $parsed = $this->parse($path);
        $parameter = $this->setterParameter($path);
        if (null === $parsed || null === $parameter) {
            throw new NotRestorableException(sprintf('Le champ « %s » n\'est pas restaurable.', $path));
        }
        $entity = $this->resolveEntity($fiche, $path);
        [, $field] = $parsed;
        $entity->{self::setterName($field)}(
            $this->denormalize($value, $parameter, $path),
        );
    }

    public function currentValue(Fiche $fiche, string $path): mixed
    {
        $parsed = $this->parse($path);
        if (null === $parsed) {
            throw new NotRestorableException(sprintf('Le champ « %s » n\'est pas restaurable.', $path));
        }
        $entity = $this->resolveEntity($fiche, $path);
        [, $field] = $parsed;
        if (!method_exists($entity, $field)) {
            throw new NotRestorableException(sprintf('Le champ « %s » n\'a pas d\'accesseur.', $path));
        }

        return $entity->{$field}();
    }

    /** @return array{string, string}|null [préfixe, champ] */
    private function parse(string $path): ?array
    {
        if ('nom' === $path) {
            return ['fiche', 'label'];
        }
        if (
            'workflow.status' === $path
            || str_contains($path, '[')
            || 1 !== substr_count($path, '.')
        ) {
            return null;
        }
        [$prefix, $field] = explode('.', $path, 2);
        if (
            !isset(self::PREFIX_CLASSES[$prefix])
            || '' === $field
            || !preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $field)
        ) {
            return null;
        }

        return [$prefix, $field];
    }

    private function setterParameter(string $path): ?\ReflectionParameter
    {
        $parsed = $this->parse($path);
        if (null === $parsed) {
            return null;
        }
        [$prefix, $field] = $parsed;
        $class = self::PREFIX_CLASSES[$prefix];
        $setter = self::setterName($field);
        if (!method_exists($class, $setter)) {
            return null;
        }
        $method = new \ReflectionMethod($class, $setter);
        if (!$method->isPublic() || 1 !== $method->getNumberOfParameters()) {
            return null;
        }
        $parameter = $method->getParameters()[0];

        return $this->isSupportedType($parameter->getType()) ? $parameter : null;
    }

    private function isSupportedType(?\ReflectionType $type): bool
    {
        if (!$type instanceof \ReflectionNamedType) {
            return false;
        }
        if ($type->isBuiltin()) {
            return in_array(
                $type->getName(),
                ['string', 'int', 'float', 'bool', 'array'],
                true,
            );
        }

        return is_subclass_of($type->getName(), \BackedEnum::class)
            || is_a($type->getName(), \DateTimeImmutable::class, true);
    }

    private function denormalize(
        mixed $value,
        \ReflectionParameter $parameter,
        string $path,
    ): mixed {
        /** @var \ReflectionNamedType $type validé par isSupportedType() */
        $type = $parameter->getType();
        $typeName = $type->getName();
        if (null === $value) {
            if (!$type->allowsNull()) {
                throw new NotRestorableException(sprintf('Le champ « %s » n\'accepte pas de valeur vide.', $path));
            }

            return null;
        }
        try {
            return match (true) {
                is_subclass_of($typeName, \BackedEnum::class) => $typeName::from($value),
                is_a($typeName, \DateTimeImmutable::class, true) => new \DateTimeImmutable((string) $value),
                'string' === $typeName => (string) $value,
                'int' === $typeName => (int) $value,
                'float' === $typeName => (float) $value,
                'bool' === $typeName => (bool) $value,
                'array' === $typeName && is_array($value) => $value,
                default => throw new NotRestorableException(sprintf('Valeur incompatible pour le champ « %s ».', $path)),
            };
        } catch (\ValueError|\TypeError|\Exception $exception) {
            if ($exception instanceof NotRestorableException) {
                throw $exception;
            }
            throw new NotRestorableException(sprintf('La valeur historisée du champ « %s » n\'est plus interprétable.', $path), previous: $exception);
        }
    }

    private function resolveEntity(Fiche $fiche, string $path): object
    {
        $parsed = $this->parse($path);
        if (null === $parsed) {
            throw new NotRestorableException(sprintf('Le champ « %s » n\'est pas restaurable.', $path));
        }
        [$prefix] = $parsed;
        if ('fiche' === $prefix) {
            return $fiche;
        }
        $detail = $this->detailFor($fiche);
        $detailClass = self::PREFIX_CLASSES[$prefix];
        $entity = match ($prefix) {
            'localisation' => $detail->localisation(),
            'administratif' => $detail instanceof Lieu
                ? $detail->administratif()
                : null,
            'tarification' => $detail instanceof Lieu
                ? $detail->tarification()
                : null,
            default => $detail::class === $detailClass ? $detail : null,
        };
        if (null === $entity) {
            throw new NotRestorableException(sprintf('Le champ « %s » ne correspond plus à la fiche.', $path));
        }

        return $entity;
    }

    private function detailFor(
        Fiche $fiche,
    ): Lieu|Activite|Restaurant|ServiceEvenementiel {
        foreach ([Lieu::class, Activite::class, Restaurant::class, ServiceEvenementiel::class] as $class) {
            $detail = $this->entityManager
                ->getRepository($class)
                ->findOneBy(['fiche' => $fiche]);
            if (null !== $detail) {
                return $detail;
            }
        }
        throw new NotRestorableException('La fiche n\'a plus d\'entité de détail.');
    }

    private static function setterName(string $field): string
    {
        return 'change'.ucfirst($field);
    }
}
