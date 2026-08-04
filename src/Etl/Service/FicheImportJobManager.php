<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Etl\Entity\FicheImportJob;
use App\Etl\Message\StartFicheImport;
use App\Pim\Enum\TypeFiche;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class FicheImportJobManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private string $importDir,
    ) {
    }

    public function createFromUpload(UploadedFile $file, TypeFiche $type, string $actor): FicheImportJob
    {
        if (!$file->isValid()) {
            throw new \DomainException('Le fichier téléversé est invalide.');
        }

        $job = new FicheImportJob($type, $file->getClientOriginalName(), $actor);

        if (!is_dir($this->importDir) && !@mkdir($this->importDir, 0775, true) && !is_dir($this->importDir)) {
            throw new \DomainException('Impossible de préparer le répertoire d’import.');
        }

        try {
            $file->move($this->importDir, $job->storagePath());
        } catch (FileException) {
            throw new \DomainException('Impossible d’enregistrer le fichier téléversé.');
        }

        $this->entityManager->persist($job);
        $this->outbox->enqueue(new StartFicheImport($job->idString()));
        $this->entityManager->flush();

        return $job;
    }
}
