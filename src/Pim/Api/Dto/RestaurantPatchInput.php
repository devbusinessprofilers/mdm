<?php

declare(strict_types=1);

namespace App\Pim\Api\Dto;

final class RestaurantPatchInput
{
    /** @var array<string, mixed> */
    private array $payload = [];

    public function setLabel(?string $value): void
    {
        $this->payload['label'] = $value;
    }
    /** @param list<string> $value */
    public function setTypesRestaurant(array $value): void
    {
        $this->payload['typesRestaurant'] = $value;
    }
    /** @param list<string> $value */
    public function setTypesCuisine(array $value): void
    {
        $this->payload['typesCuisine'] = $value;
    }
    /** @param list<string> $value */
    public function setSpecificitesAlimentaires(array $value): void
    {
        $this->payload['specificitesAlimentaires'] = $value;
    }
    /** @param list<string> $value */
    public function setTypesEvenement(array $value): void
    {
        $this->payload['typesEvenement'] = $value;
    }

    public function setSiteOfficiel(?string $value): void
    {
        $this->payload['siteOfficiel'] = $value;
    }

    public function setPrivatisationTotale(?bool $value): void
    {
        $this->payload['privatisationTotale'] = $value;
    }

    public function setPrivatisationPartielle(?bool $value): void
    {
        $this->payload['privatisationPartielle'] = $value;
    }
    /** @param list<string> $value */
    public function setJoursOuverture(array $value): void
    {
        $this->payload['joursOuverture'] = $value;
    }

    public function setHeureOuverture(?string $value): void
    {
        $this->payload['heureOuverture'] = $value;
    }

    public function setHeureFermeture(?string $value): void
    {
        $this->payload['heureFermeture'] = $value;
    }
    /** @param array<string, array<string, mixed>>|null $value */
    public function setHorairesJours(?array $value): void
    {
        $this->payload['horairesJours'] = $value ?? [];
    }
    /** @param list<array<string, mixed>> $value */
    public function setPeriodesFermeture(array $value): void
    {
        $this->payload['periodesFermeture'] = $value;
    }
    /** @param array<string, mixed>|null $value */
    public function setLocalisation(?array $value): void
    {
        $this->payload['localisation'] = $value;
    }
    /** @param list<array<string, mixed>> $value */
    public function setAcces(array $value): void
    {
        $this->payload['acces'] = $value;
    }

    public function setAccesPmr(?bool $value): void
    {
        $this->payload['accesPmr'] = $value;
    }

    public function setToilettesPmr(?bool $value): void
    {
        $this->payload['toilettesPmr'] = $value;
    }

    public function setDescriptionGenerale(?string $value): void
    {
        $this->payload['descriptionGenerale'] = $value;
    }
    /** @param list<string> $value */
    public function setAtouts(array $value): void
    {
        $this->payload['atouts'] = $value;
    }

    public function setCapaciteAssiseMax(?int $value): void
    {
        $this->payload['capaciteAssiseMax'] = $value;
    }

    public function setCapaciteEspacePrivatisable(?int $value): void
    {
        $this->payload['capaciteEspacePrivatisable'] = $value;
    }

    public function setCapaciteBanquet(?int $value): void
    {
        $this->payload['capaciteBanquet'] = $value;
    }

    public function setCapaciteCocktail(?int $value): void
    {
        $this->payload['capaciteCocktail'] = $value;
    }
    /** @param list<array<string, mixed>> $value */
    public function setSalles(array $value): void
    {
        $this->payload['salles'] = $value;
    }
    /** @param list<string> $value */
    public function setServices(array $value): void
    {
        $this->payload['services'] = $value;
    }
    /** @param list<string> $value */
    public function setEquipements(array $value): void
    {
        $this->payload['equipements'] = $value;
    }
    /** @param list<string> $value */
    public function setEngagementsRse(array $value): void
    {
        $this->payload['engagementsRse'] = $value;
    }

    public function setYoutubeUrl(?string $value): void
    {
        $this->payload['youtubeUrl'] = $value;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }
}
