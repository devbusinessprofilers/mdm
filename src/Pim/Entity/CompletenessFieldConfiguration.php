<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Enum\CompletenessFormula;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\CompletenessFieldConfigurationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompletenessFieldConfigurationRepository::class)]
#[ORM\Table(name: 'pim_completeness_field_configuration')]
#[ORM\UniqueConstraint(name: 'UNIQ_COMPLETENESS_TYPE_CODE', columns: ['fiche_type', 'field_code'])]
#[ORM\Index(name: 'IDX_COMPLETENESS_TYPE_ACTIVE', columns: ['fiche_type', 'active'])]
class CompletenessFieldConfiguration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'fiche_type', length: 32, enumType: TypeFiche::class)]
    private TypeFiche $ficheType;

    #[ORM\Column(name: 'field_code', length: 96)]
    private string $fieldCode;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(length: 32, enumType: CompletenessFormula::class)]
    private CompletenessFormula $formula = CompletenessFormula::Presence;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, options: ['default' => '1.00'])]
    private string $weight = '1.00';

    #[ORM\Column(name: 'target_length_override', nullable: true, options: ['unsigned' => true])]
    private ?int $targetLengthOverride = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $marketplace = true;

    #[ORM\Column(name: 'thematic_sites', options: ['default' => true])]
    private bool $thematicSites = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $salesforce = true;

    #[ORM\Column(name: 'provider_portal', options: ['default' => true])]
    private bool $providerPortal = true;

    public function __construct(TypeFiche $type, string $fieldCode, string $label)
    {
        $this->ficheType = $type;
        $this->fieldCode = strtoupper(trim($fieldCode));
        $this->label = trim($label);
    }

    public function id(): ?int { return $this->id; }
    public function ficheType(): TypeFiche { return $this->ficheType; }
    public function fieldCode(): string { return $this->fieldCode; }
    public function label(): string { return $this->label; }
    public function formula(): CompletenessFormula { return $this->formula; }
    public function weight(): float { return (float) $this->weight; }
    public function targetLengthOverride(): ?int { return $this->targetLengthOverride; }
    public function active(): bool { return $this->active; }
    public function marketplace(): bool { return $this->marketplace; }
    public function thematicSites(): bool { return $this->thematicSites; }
    public function salesforce(): bool { return $this->salesforce; }
    public function providerPortal(): bool { return $this->providerPortal; }

    public function refreshLabel(string $label): void
    {
        $this->label = trim($label);
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    /** @return array{formula: string, weight: float, targetLengthOverride: int|null, active: bool, marketplace: bool, thematicSites: bool, salesforce: bool, providerPortal: bool} */
    public function snapshot(): array
    {
        return [
            'formula' => $this->formula->value,
            'weight' => $this->weight(),
            'targetLengthOverride' => $this->targetLengthOverride,
            'active' => $this->active,
            'marketplace' => $this->marketplace,
            'thematicSites' => $this->thematicSites,
            'salesforce' => $this->salesforce,
            'providerPortal' => $this->providerPortal,
        ];
    }

    public function configure(
        CompletenessFormula $formula,
        float $weight,
        ?int $targetLengthOverride,
        bool $active,
        bool $marketplace,
        bool $thematicSites,
        bool $salesforce,
        bool $providerPortal,
    ): void {
        if (!is_finite($weight) || $weight < 0 || $weight > 100000) {
            throw new \InvalidArgumentException('Le poids doit être compris entre 0 et 100 000.');
        }
        if (null !== $targetLengthOverride && $targetLengthOverride < 1) {
            throw new \InvalidArgumentException('La longueur cible doit être strictement positive.');
        }
        $this->formula = $formula;
        $this->weight = number_format($weight, 2, '.', '');
        $this->targetLengthOverride = $targetLengthOverride;
        $this->active = $active;
        $this->marketplace = $marketplace;
        $this->thematicSites = $thematicSites;
        $this->salesforce = $salesforce;
        $this->providerPortal = $providerPortal;
    }
}
