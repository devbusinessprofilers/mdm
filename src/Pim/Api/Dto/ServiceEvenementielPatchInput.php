<?php

declare(strict_types=1);

namespace App\Pim\Api\Dto;

use App\Pim\Lov\ServiceLovCatalog;

final class ServiceEvenementielPatchInput
{
    /** @var array<string, mixed> */
    private array $payload = [];

    public function setLabel(?string $value): void
    {
        $this->payload["label"] = $value;
    }

    /** @param list<string> $value */
    public function setPrestations(array $value): void
    {
        $this->payload["prestations"] = $value;
    }

    /**
     * Contrat API à plat : les codes sont répartis sur les champs de
     * formulaire par famille, les familles absentes sont vidées.
     *
     * @param list<string> $value
     */
    public function setSousPrestations(array $value): void
    {
        $parChamp = array_fill_keys(
            array_values(ServiceLovCatalog::SOUS_PRESTATION_FIELDS),
            [],
        );
        foreach ($value as $code) {
            $attribute = ServiceLovCatalog::sousPrestationAttributeOf((string) $code);
            $field = ServiceLovCatalog::SOUS_PRESTATION_FIELDS[$attribute]
                ?? throw new \InvalidArgumentException("Sous-prestation Service inconnue.");
            $parChamp[$field][] = $code;
        }
        foreach ($parChamp as $field => $codes) {
            $this->payload[$field] = $codes;
        }
    }

    public function setParticipantsMin(?int $value): void
    {
        $this->payload["participantsMin"] = $value;
    }

    public function setParticipantsMax(?int $value): void
    {
        $this->payload["participantsMax"] = $value;
    }

    public function setDureeMinutes(?int $value): void
    {
        $this->payload["dureeMinutes"] = $value;
    }

    public function setPrestataireEsat(?bool $value): void
    {
        $this->payload["prestataireEsat"] = $value;
    }

    public function setDemarcheRse(?bool $value): void
    {
        $this->payload["demarcheRse"] = $value;
    }

    public function setDescriptionGenerale(?string $value): void
    {
        $this->payload["descriptionGenerale"] = $value;
    }

    public function setAdapteFemmesEnceintes(?bool $value): void
    {
        $this->payload["adapteFemmesEnceintes"] = $value;
    }

    public function setAdapteMalentendants(?bool $value): void
    {
        $this->payload["adapteMalentendants"] = $value;
    }

    public function setAdapteMalvoyants(?bool $value): void
    {
        $this->payload["adapteMalvoyants"] = $value;
    }

    public function setMaterielInclus(?bool $value): void
    {
        $this->payload["materielInclus"] = $value;
    }

    public function setEquipementParticipantsRequis(?bool $value): void
    {
        $this->payload["equipementParticipantsRequis"] = $value;
    }

    public function setEquipementReceptionRequis(?bool $value): void
    {
        $this->payload["equipementReceptionRequis"] = $value;
    }

    public function setContraintesLogistiques(?bool $value): void
    {
        $this->payload["contraintesLogistiques"] = $value;
    }

    public function setModeIntervention(?string $value): void
    {
        $this->payload["modeIntervention"] = $value;
    }

    /** @param list<string> $value */
    public function setPaysMobiles(array $value): void
    {
        $this->payload["paysMobiles"] = $value;
    }

    /** @param list<string> $value */
    public function setRegionsMobiles(array $value): void
    {
        $this->payload["regionsMobiles"] = $value;
    }

    /** @param list<string> $value */
    public function setDepartementsMobiles(array $value): void
    {
        $this->payload["departementsMobiles"] = $value;
    }

    /** @param array<string, mixed>|null $value */
    public function setLocalisation(?array $value): void
    {
        $this->payload["localisation"] = $value;
    }

    public function setTarifParPrestation(?float $value): void
    {
        $this->payload["tarifParPrestation"] = $value;
    }

    public function setTarifParPersonne(?float $value): void
    {
        $this->payload["tarifParPersonne"] = $value;
    }

    public function setTarifParJour(?float $value): void
    {
        $this->payload["tarifParJour"] = $value;
    }

    public function setTarifParDemiJournee(?float $value): void
    {
        $this->payload["tarifParDemiJournee"] = $value;
    }

    public function setTarifParHeure(?float $value): void
    {
        $this->payload["tarifParHeure"] = $value;
    }

    public function setSurDevis(?bool $value): void
    {
        $this->payload["surDevis"] = $value;
    }

    public function setYoutubeUrl(?string $value): void
    {
        $this->payload["youtubeUrl"] = $value;
    }

    /** @param list<array<string, mixed>> $value */
    public function setAcces(array $value): void
    {
        $this->payload["acces"] = $value;
    }

    public function setAccesPmr(?bool $value): void
    {
        $this->payload["accesPmr"] = $value;
    }

    public function setMaterielAdaptePmr(?bool $value): void
    {
        $this->payload["materielAdaptePmr"] = $value;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }
}
