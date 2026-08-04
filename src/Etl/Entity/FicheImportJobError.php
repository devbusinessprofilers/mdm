<?php

declare(strict_types=1);

namespace App\Etl\Entity;

use App\Etl\Repository\FicheImportJobErrorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FicheImportJobErrorRepository::class)]
#[ORM\Table(name: 'etl_import_job_error')]
#[ORM\Index(name: 'IDX_ETL_IMPORT_JOB_ERROR_JOB_LINE', columns: ['job_id', 'line_number'])]
class FicheImportJobError
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FicheImportJob::class)]
    #[ORM\JoinColumn(name: 'job_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private FicheImportJob $job;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $lineNumber;

    #[ORM\Column(length: 96, nullable: true)]
    private ?string $columnName;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(FicheImportJob $job, int $lineNumber, ?string $columnName, string $message)
    {
        $this->job = $job;
        $this->lineNumber = $lineNumber;
        $this->columnName = null !== $columnName ? mb_substr($columnName, 0, 96) : null;
        $this->message = $message;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function job(): FicheImportJob
    {
        return $this->job;
    }

    public function lineNumber(): int
    {
        return $this->lineNumber;
    }

    public function columnName(): ?string
    {
        return $this->columnName;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
