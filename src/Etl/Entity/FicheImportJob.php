<?php

declare(strict_types=1);

namespace App\Etl\Entity;

use App\Etl\Enum\ImportJobStatus;
use App\Etl\Repository\FicheImportJobRepository;
use App\Pim\Enum\TypeFiche;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: FicheImportJobRepository::class)]
#[ORM\Table(name: 'etl_import_job')]
#[ORM\HasLifecycleCallbacks]
class FicheImportJob
{
    use TimestampableTrait;

    /** Import historique : la cellule vide ne modifie rien, NULL vide le champ. */
    public const MODE_COMPLEMENT = 'complement';
    /** Import en masse du fichier d'export : le fichier fait foi, cellule vide = champ vidé, libellés LOV acceptés. */
    public const MODE_ECRASEMENT = 'ecrasement';

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 32, enumType: TypeFiche::class)]
    private TypeFiche $type;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 255)]
    private string $storagePath;

    #[ORM\Column(length: 32, enumType: ImportJobStatus::class)]
    private ImportJobStatus $status = ImportJobStatus::EnAttente;

    #[ORM\Column(nullable: true, options: ['unsigned' => true])]
    private ?int $totalLines = null;

    #[ORM\Column(options: ['unsigned' => true, 'default' => 0])]
    private int $processedLines = 0;

    #[ORM\Column(options: ['unsigned' => true, 'default' => 0])]
    private int $createdCount = 0;

    #[ORM\Column(options: ['unsigned' => true, 'default' => 0])]
    private int $updatedCount = 0;

    #[ORM\Column(options: ['unsigned' => true, 'default' => 0])]
    private int $errorCount = 0;

    #[ORM\Column(options: ['unsigned' => true, 'default' => 0])]
    private int $lastProcessedLine = 0;

    #[ORM\Column(length: 180)]
    private string $createdBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $failureMessage = null;

    #[ORM\Column(length: 16, options: ['default' => self::MODE_COMPLEMENT])]
    private string $mode = self::MODE_COMPLEMENT;

    public function __construct(TypeFiche $type, string $originalFilename, string $createdBy, string $extension = 'csv', string $mode = self::MODE_COMPLEMENT)
    {
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            throw new \DomainException('Extension de fichier d’import non prise en charge.');
        }
        if (!in_array($mode, [self::MODE_COMPLEMENT, self::MODE_ECRASEMENT], true)) {
            throw new \DomainException('Mode d’import inconnu.');
        }
        $this->mode = $mode;

        $this->id = new Ulid();
        $this->type = $type;
        $this->originalFilename = mb_substr($originalFilename, 0, 255);
        $this->storagePath = $this->id.'.'.$extension;
        $this->createdBy = $createdBy;
        $this->initializeTimestamps();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function idString(): string
    {
        return (string) $this->id;
    }

    public function type(): TypeFiche
    {
        return $this->type;
    }

    public function originalFilename(): string
    {
        return $this->originalFilename;
    }

    public function storagePath(): string
    {
        return $this->storagePath;
    }

    public function status(): ImportJobStatus
    {
        return $this->status;
    }

    public function totalLines(): ?int
    {
        return $this->totalLines;
    }

    public function processedLines(): int
    {
        return $this->processedLines;
    }

    public function createdCount(): int
    {
        return $this->createdCount;
    }

    public function updatedCount(): int
    {
        return $this->updatedCount;
    }

    public function errorCount(): int
    {
        return $this->errorCount;
    }

    public function lastProcessedLine(): int
    {
        return $this->lastProcessedLine;
    }

    public function createdBy(): string
    {
        return $this->createdBy;
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function failureMessage(): ?string
    {
        return $this->failureMessage;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function estEcrasement(): bool
    {
        return self::MODE_ECRASEMENT === $this->mode;
    }

    public function start(int $totalLines): void
    {
        if (ImportJobStatus::EnAttente !== $this->status) {
            throw new \DomainException('Le job d’import a déjà démarré.');
        }

        $this->status = ImportJobStatus::EnCours;
        $this->totalLines = $totalLines;
        $this->startedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function recordBatch(int $lastLine, int $created, int $updated, int $errors): void
    {
        $this->lastProcessedLine = max($this->lastProcessedLine, $lastLine);
        $this->processedLines += $created + $updated + $errors;
        $this->createdCount += $created;
        $this->updatedCount += $updated;
        $this->errorCount += $errors;
        $this->touch();
    }

    public function finish(): void
    {
        $this->status = 0 === $this->errorCount ? ImportJobStatus::Termine : ImportJobStatus::TermineAvecErreurs;
        $this->finishedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function fail(string $message): void
    {
        $this->status = ImportJobStatus::Echoue;
        $this->failureMessage = $message;
        $this->finishedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [ImportJobStatus::Termine, ImportJobStatus::TermineAvecErreurs, ImportJobStatus::Echoue], true);
    }
}
