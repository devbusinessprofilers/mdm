<?php

declare(strict_types=1);

namespace App\Shared\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait TimestampableTrait
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $now = new \DateTimeImmutable();

        if (!isset($this->createdAt)) {
            $this->createdAt = $now;
        }

        $this->updatedAt = $now;
    }

    /**
     * Horodatage technique au flush : n'appelle volontairement pas touch(),
     * que les lignes détail des fiches (Lieu, Restaurant…) surchargent pour
     * propager un markChanged métier. Un UPDATE Doctrine n'est pas une
     * modification métier : il ne doit jamais repasser une fiche « en cours ».
     */
    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    protected function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
