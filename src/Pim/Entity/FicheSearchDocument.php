<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Repository\FicheSearchDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FicheSearchDocumentRepository::class)]
#[ORM\Table(name: 'pim_fiche_search')]
#[ORM\Index(name: 'FTX_PIM_FICHE_SEARCH', columns: ['content'], flags: ['fulltext'])]
class FicheSearchDocument
{
    public function __construct(
        #[ORM\Id]
        #[ORM\OneToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private Fiche $fiche,
        #[ORM\Column(type: Types::TEXT)]
        private string $content,
        #[ORM\Column(options: ['unsigned' => true])]
        private int $sourceVersion,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $indexedAt = new \DateTimeImmutable(),
    ) {
    }

    public function fiche(): Fiche
    {
        return $this->fiche;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function sourceVersion(): int
    {
        return $this->sourceVersion;
    }

    public function reindex(string $content, int $version): void
    {
        $this->content = $content;
        $this->sourceVersion = $version;
        $this->indexedAt = new \DateTimeImmutable();
    }
}
