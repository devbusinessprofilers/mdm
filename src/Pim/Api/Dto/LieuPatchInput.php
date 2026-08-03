<?php

declare(strict_types=1);

namespace App\Pim\Api\Dto;

use ApiPlatform\Metadata\ApiProperty;

/**
 * Payload partiel d'une fiche Lieu. Seules les propriétés présentes sont modifiées.
 * Les médias, le statut et la complétude disposent de traitements dédiés.
 */
final class LieuPatchInput
{
    private bool $labelDefined = false;
    private bool $generaleTypologieDefined = false;
    private bool $generaleWebsiteUrlDefined = false;
    #[ApiProperty(example: 'Hôtel des Lumières')]
    private ?string $label = null;
    /** @var list<string> */
    private array $generaleTypologie = [];
    private ?string $generaleWebsiteUrl = null;
    /** @var array<string, mixed>|null */
    public ?array $informationsGenerales = null;
    /** @var array<string, mixed>|null */
    public ?array $disponibilites = null;
    /** @var array<string, mixed>|null */
    public ?array $accessibiliteDescription = null;
    /** @var array<string, mixed>|null */
    public ?array $hebergement = null;
    /** @var array<string, mixed>|null */
    public ?array $syntheseSalles = null;
    /** @var array<string, mixed>|null */
    public ?array $equipementsServices = null;
    /** @var array<string, mixed>|null */
    public ?array $rse = null;
    /** @var array<string, mixed>|null */
    public ?array $loisirs = null;
    /** @var array<string, mixed>|null */
    public ?array $restauration = null;
    /** @var array<string, mixed>|null */
    public ?array $visibilite = null;
    /** @var array<string, mixed>|null */
    public ?array $localisation = null;
    /** @var array<string, mixed>|null */
    public ?array $administratif = null;
    /** @var array<string, mixed>|null */
    public ?array $tarification = null;
    /** @var list<array<string, mixed>>|null */
    public ?array $salles = null;
    /** @var list<array<string, mixed>>|null */
    public ?array $periodesFermeture = null;
    /** @var list<array<string, mixed>>|null */
    public ?array $acces = null;

    public function setLabel(?string $label): void
    {
        $this->labelDefined = true;
        $this->label = $label;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    /** @param list<string> $values */
    public function setGeneraleTypologie(array $values): void
    {
        $this->generaleTypologieDefined = true;
        $this->generaleTypologie = $values;
    }

    /** @return list<string> */
    public function getGeneraleTypologie(): array
    {
        return $this->generaleTypologie;
    }

    public function setGeneraleWebsiteUrl(?string $url): void
    {
        $this->generaleWebsiteUrlDefined = true;
        $this->generaleWebsiteUrl = $url;
    }

    public function getGeneraleWebsiteUrl(): ?string
    {
        return $this->generaleWebsiteUrl;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $payload = [];
        if ($this->labelDefined) {
            $payload['label'] = $this->label;
        }
        if ($this->generaleTypologieDefined) {
            $payload['generaleTypologie'] = $this->generaleTypologie;
        }
        if ($this->generaleWebsiteUrlDefined) {
            $payload['generaleWebsiteUrl'] = $this->generaleWebsiteUrl;
        }
        foreach (
            [
                'informationsGenerales',
                'disponibilites',
                'accessibiliteDescription',
                'hebergement',
                'syntheseSalles',
                'equipementsServices',
                'rse',
                'loisirs',
                'restauration',
                'visibilite',
                'localisation',
                'administratif',
                'tarification',
                'salles',
                'periodesFermeture',
                'acces',
            ] as $property
        ) {
            if (null !== $this->{$property}) {
                $payload[$property] = $this->{$property};
            }
        }
        foreach (
            [
                ['informationsGenerales', 'evenementsPredilection'],
                ['disponibilites', 'joursOuverture'],
            ] as [$section, $field]
        ) {
            if (
                isset($payload[$section][$field])
                && is_string($payload[$section][$field])
            ) {
                $payload[$section][$field] = [$payload[$section][$field]];
            }
        }
        foreach (
            [
                [
                    'informationsGenerales',
                    'generaleGamme',
                    'evenementsPredilection',
                ],
                ['disponibilites', 'dispoJourOuverture', 'joursOuverture'],
            ] as [$section, $legacy, $field]
        ) {
            if (!isset($payload[$section][$legacy])) {
                continue;
            }
            $payload[$section][$field] = is_array($payload[$section][$legacy])
                ? $payload[$section][$legacy]
                : [$payload[$section][$legacy]];
            unset($payload[$section][$legacy]);
        }

        return $payload;
    }
}
