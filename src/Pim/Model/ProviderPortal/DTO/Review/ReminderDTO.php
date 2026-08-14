<?php

namespace App\Pim\Model\ProviderPortal\DTO\Review;

class ReminderDTO
{
    public string $companyName;

    public string $eventName;

    public string $eventReference;

    public string $placeName;

    public \DateTimeInterface $date;

    public ?\DateTimeInterface $remindDate = null;

    public ?string $logoUrl = null;

    public static function mock(int $index = 1): self
    {
        $data = new self();

        $data->companyName = sprintf('Company %d', $index);
        $data->eventName = sprintf('Event %d', $index);
        $data->eventReference = sprintf('EVT-PLX-%d', $index);
        $data->date = new \DateTimeImmutable('2023-01-01');
        $data->placeName = sprintf('Fiche %d', $index);

        if (rand(1, 5) > 1) {
            $data->logoUrl = 'provider_portal/img/mock/company-logo.png';
        }

        if (1 === rand(1, 5)) {
            $data->remindDate = (new \DateTimeImmutable('2026-01-01'));
        }

        return $data;
    }
}
