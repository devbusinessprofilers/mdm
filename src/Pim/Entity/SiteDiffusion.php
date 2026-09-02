<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\SiteDiffusionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Référentiel des sites de diffusion : les canaux sur lesquels une fiche peut
 * être diffusée. Les groupes sont des regroupements commerciaux libres, pas
 * les canaux de complétude. Un site obligatoire est retenu d'office et ne se
 * décoche pas ; un site payant est signalé comme tel dans les écrans.
 */
#[ORM\Entity(repositoryClass: SiteDiffusionRepository::class)]
#[ORM\Table(name: 'pim_site_diffusion')]
#[ORM\UniqueConstraint(name: 'UNIQ_PIM_SITE_DIFFUSION_CODE', columns: ['code'])]
#[ORM\Index(name: 'IDX_PIM_SITE_DIFFUSION_ACTIF', columns: ['actif', 'position'])]
class SiteDiffusion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(length: 128)]
    private string $groupe;

    #[ORM\Column(options: ['default' => false])]
    private bool $obligatoire = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $payant = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true, 'default' => 0])]
    private int $position = 0;

    /** @var list<string> Valeurs TypeFiche pour lesquelles le site est présélectionné à la création. */
    #[ORM\Column(name: 'gammes_par_defaut', type: Types::JSON)]
    private array $gammesParDefaut = [];

    /**
     * @var list<array<string, string|int>> Critères géographiques (CritereGeo
     *      sérialisés, OU logique) : zone dont les fiches sont rattachées
     *      automatiquement au site. Vide = jamais attribué automatiquement.
     */
    #[ORM\Column(name: 'criteres_geo', type: Types::JSON)]
    private array $criteresGeo = [];

    /**
     * @param list<TypeFiche>  $gammesParDefaut
     * @param list<CritereGeo> $criteresGeo
     */
    public function __construct(
        string $code,
        string $label,
        string $groupe,
        bool $obligatoire = false,
        bool $payant = false,
        int $position = 0,
        array $gammesParDefaut = [],
        array $criteresGeo = [],
    ) {
        $this->code = $code;
        $this->label = $label;
        $this->groupe = $groupe;
        $this->obligatoire = $obligatoire;
        $this->payant = $payant;
        $this->position = max(0, $position);
        $this->gammesParDefaut = array_values(array_unique(array_map(
            static fn (TypeFiche $type): string => $type->value,
            $gammesParDefaut,
        )));
        $this->changeCriteresGeo($criteresGeo);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function groupe(): string
    {
        return $this->groupe;
    }

    public function obligatoire(): bool
    {
        return $this->obligatoire;
    }

    public function payant(): bool
    {
        return $this->payant;
    }

    public function actif(): bool
    {
        return $this->actif;
    }

    public function position(): int
    {
        return $this->position;
    }

    /** @return list<TypeFiche> */
    public function gammesParDefaut(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $value): ?TypeFiche => TypeFiche::tryFrom($value),
            $this->gammesParDefaut,
        )));
    }

    public function estParDefautPour(TypeFiche $type): bool
    {
        return in_array($type->value, $this->gammesParDefaut, true);
    }

    /** @return list<CritereGeo> Les entrées sérialisées devenues illisibles sont ignorées. */
    public function criteresGeo(): array
    {
        return array_values(array_filter(array_map(
            static fn (array $critere): ?CritereGeo => CritereGeo::fromArray($critere),
            $this->criteresGeo,
        )));
    }

    /** @param list<CritereGeo> $criteres */
    public function changeCriteresGeo(array $criteres): void
    {
        $this->criteresGeo = array_values(array_map(
            static fn (CritereGeo $critere): array => $critere->toArray(),
            $criteres,
        ));
    }

    public function changeLabel(string $label): void
    {
        $this->label = trim($label);
    }

    public function changeGroupe(string $groupe): void
    {
        $this->groupe = trim($groupe);
    }

    public function changePosition(int $position): void
    {
        $this->position = max(0, $position);
    }

    public function changeObligatoire(bool $obligatoire): void
    {
        $this->obligatoire = $obligatoire;
    }

    public function changePayant(bool $payant): void
    {
        $this->payant = $payant;
    }

    /** @param list<TypeFiche> $gammes */
    public function changeGammesParDefaut(array $gammes): void
    {
        $this->gammesParDefaut = array_values(array_unique(array_map(
            static fn (TypeFiche $type): string => $type->value,
            $gammes,
        )));
    }

    public function activate(): void
    {
        $this->actif = true;
    }

    public function deactivate(): void
    {
        $this->actif = false;
    }
}
