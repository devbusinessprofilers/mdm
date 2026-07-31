<?php

declare(strict_types=1);

namespace App\Audit\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'audit_change')]
#[
    ORM\Index(
        name: 'IDX_AUDIT_CHANGE_REVISION_PATH',
        columns: ['revision_id', 'path'],
    ),
]
final class AuditChange
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'changes')] #[
            ORM\JoinColumn(nullable: false, onDelete: 'CASCADE'),
        ]
        private AuditRevision $revision,
        #[ORM\Column(length: 512)] private string $path,
        #[
            ORM\Column(type: Types::JSON, nullable: true),
        ]
        private mixed $oldValue,
        #[
            ORM\Column(type: Types::JSON, nullable: true),
        ]
        private mixed $newValue,
    ) {
        $this->id = new Ulid();
        $revision->addChange($this);
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function oldValue(): mixed
    {
        return $this->oldValue;
    }

    public function newValue(): mixed
    {
        return $this->newValue;
    }
}
