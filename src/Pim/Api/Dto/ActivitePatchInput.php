<?php

declare(strict_types=1);

namespace App\Pim\Api\Dto;

final class ActivitePatchInput
{
    /** @var array<string,mixed> */
    private array $payload = [];

    public function setLabel(?string $v): void
    {
        $this->payload['label'] = $v;
    }

    public function setPrestataireCode(?string $v): void
    {
        $this->payload['prestataireCode'] = $v;
    }

    /** @param list<string> $v */
    public function setTypes(array $v): void
    {
        $this->payload['types'] = $v;
    }

    /** @param list<string> $v */
    public function setThematiques(array $v): void
    {
        $this->payload['thematiques'] = $v;
    }

    /** @param list<string> $v */
    public function setSousThematiques(array $v): void
    {
        $this->payload['sousThematiques'] = $v;
    }

    /** @param list<string> $v */
    public function setLangues(array $v): void
    {
        $this->payload['langues'] = $v;
    }

    /** @param list<string> $v */
    public function setEngagementsRse(array $v): void
    {
        $this->payload['engagementsRse'] = $v;
    }

    public function setModeIntervention(?string $v): void
    {
        $this->payload['modeIntervention'] = $v;
    }

    public function setTouteFrance(bool $v): void
    {
        $this->payload['touteFrance'] = $v;
    }

    /** @param list<string> $v */
    public function setPaysMobiles(array $v): void
    {
        $this->payload['paysMobiles'] = $v;
    }

    /** @param list<string> $v */
    public function setRegionsMobiles(array $v): void
    {
        $this->payload['regionsMobiles'] = $v;
    }

    /** @param list<string> $v */
    public function setDepartementsMobiles(array $v): void
    {
        $this->payload['departementsMobiles'] = $v;
    }

    public function setDescriptionGenerale(?string $v): void
    {
        $this->payload['descriptionGenerale'] = $v;
    }

    public function setComprendPrestation(?string $v): void
    {
        $this->payload['comprendPrestation'] = $v;
    }

    /** @param list<string> $v */
    public function setObjectifs(array $v): void
    {
        $this->payload['objectifs'] = $v;
    }

    public function setParticipantsMin(?int $v): void
    {
        $this->payload['participantsMin'] = $v;
    }

    public function setParticipantsMax(?int $v): void
    {
        $this->payload['participantsMax'] = $v;
    }

    public function setDureeMinMinutes(?int $v): void
    {
        $this->payload['dureeMinMinutes'] = $v;
    }

    public function setDureeMaxMinutes(?int $v): void
    {
        $this->payload['dureeMaxMinutes'] = $v;
    }

    /** @param list<string> $v */
    public function setPlus(array $v): void
    {
        $this->payload['plus'] = $v;
    }

    public function setTarifParPersonne(?string $v): void
    {
        $this->payload['tarifParPersonne'] = $v;
    }

    public function setYoutubeUrl(?string $v): void
    {
        $this->payload['youtubeUrl'] = $v;
    }

    /** @param array<string,mixed>|null $v */
    public function setLocalisation(?array $v): void
    {
        $this->payload['localisation'] = $v;
    }

    /** @param list<array<string,mixed>> $v */
    public function setOffres(array $v): void
    {
        $this->payload['offres'] = $v;
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return $this->payload;
    }
}
