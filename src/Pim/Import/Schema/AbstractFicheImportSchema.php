<?php

declare(strict_types=1);

namespace App\Pim\Import\Schema;

abstract class AbstractFicheImportSchema implements FicheImportSchemaInterface
{
    /** @return list<ColumnDefinition> */
    protected function commonColumns(): array
    {
        return [
            new ColumnDefinition('code', ColumnKind::Int, 'code', help: 'laisser vide pour créer, code existant pour mettre à jour'),
            new ColumnDefinition('label', ColumnKind::Text, 'label', required: true, help: 'libellé de la fiche, obligatoire à la création'),
            $this->locText('pays'),
            $this->locText('countryCode', 'code ISO 2 lettres, ex FR'),
            $this->locText('region'),
            $this->locText('departement'),
            $this->locText('ruePostale'),
            $this->locText('codePostal'),
            $this->locText('ville'),
            $this->locText('arrondissement'),
            new ColumnDefinition('localisation_latitude', ColumnKind::Decimal, 'latitude', targetPath: 'localisation', help: 'décimal, point comme séparateur'),
            new ColumnDefinition('localisation_longitude', ColumnKind::Decimal, 'longitude', targetPath: 'localisation', help: 'décimal, point comme séparateur'),
            new ColumnDefinition('attribution_visibilite', ColumnKind::SitesDiffusion, 'sitesDiffusion', help: 'libellés (ou codes) du référentiel des sites de diffusion, séparés par | ; les sites obligatoires sont réappliqués'),
        ];
    }

    protected static function snake(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    protected function locText(string $target, string $help = ''): ColumnDefinition
    {
        return new ColumnDefinition('localisation_'.self::snake($target), ColumnKind::Text, $target, targetPath: 'localisation', help: $help);
    }

    protected function text(string $target, ?int $maxLength = null, string $help = '', ?string $targetPath = null): ColumnDefinition
    {
        return new ColumnDefinition(self::prefixed($targetPath, $target), ColumnKind::Text, $target, maxLength: $maxLength, help: $help, targetPath: $targetPath);
    }

    protected function int(string $target, string $help = ''): ColumnDefinition
    {
        return new ColumnDefinition(self::snake($target), ColumnKind::Int, $target, help: $help);
    }

    protected function bool(string $target, string $help = '', ?string $targetPath = null): ColumnDefinition
    {
        return new ColumnDefinition(self::prefixed($targetPath, $target), ColumnKind::Bool, $target, help: $help, targetPath: $targetPath, nullable: false);
    }

    protected function boolNull(string $target, string $help = '', ?string $targetPath = null): ColumnDefinition
    {
        return new ColumnDefinition(self::prefixed($targetPath, $target), ColumnKind::Bool, $target, help: $help, targetPath: $targetPath);
    }

    protected function decimal(string $target, ?string $targetPath = null): ColumnDefinition
    {
        return new ColumnDefinition(self::prefixed($targetPath, $target), ColumnKind::Decimal, $target, help: 'décimal, point comme séparateur', targetPath: $targetPath);
    }

    protected function float(string $target): ColumnDefinition
    {
        return new ColumnDefinition(self::snake($target), ColumnKind::Float, $target, help: 'décimal, point comme séparateur');
    }

    /** @param class-string<\BackedEnum> $enumClass */
    protected function enum(string $target, string $enumClass, string $help = ''): ColumnDefinition
    {
        $values = implode('|', array_map(static fn (\BackedEnum $case): string => (string) $case->value, $enumClass::cases()));

        return new ColumnDefinition(self::snake($target), ColumnKind::Enum, $target, enumClass: $enumClass, help: '' !== $help ? $help : 'valeurs : '.$values);
    }

    protected function lovMono(string $target, string $attribute, ?string $targetPath = null): ColumnDefinition
    {
        return new ColumnDefinition(self::prefixed($targetPath, $target), ColumnKind::LovMono, $target, lovAttribute: $attribute, help: 'un code, voir LISTES DE VALEURS : '.$attribute, targetPath: $targetPath);
    }

    protected function lovMulti(string $target, string $attribute): ColumnDefinition
    {
        return new ColumnDefinition(self::snake($target), ColumnKind::LovMulti, $target, lovAttribute: $attribute, help: 'codes séparés par |, voir LISTES DE VALEURS : '.$attribute);
    }

    protected function list(string $target, string $help = 'valeurs libres séparées par |'): ColumnDefinition
    {
        return new ColumnDefinition(self::snake($target), ColumnKind::StringList, $target, help: $help);
    }

    private static function prefixed(?string $targetPath, string $target): string
    {
        $prefix = match ($targetPath) {
            'administratif' => 'admin_',
            'tarification' => 'tarif_',
            default => '',
        };

        return $prefix.self::snake($target);
    }
}
