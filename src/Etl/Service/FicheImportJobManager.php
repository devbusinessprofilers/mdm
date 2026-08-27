<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Etl\Entity\FicheImportJob;
use App\Etl\Message\StartFicheImport;
use App\Pim\Enum\TypeFiche;
use App\Pim\Export\FicheExportXlsxGenerator;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use OpenSpout\Reader\XLSX\Reader;
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

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            $extension = str_contains((string) $file->getMimeType(), 'spreadsheetml') ? 'xlsx' : 'csv';
        }

        $job = new FicheImportJob($type, $file->getClientOriginalName(), $actor, $extension);

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

    /**
     * Import en masse d'un fichier d'export du référentiel (mode écrasement) :
     * un job par feuille de gamme présente (Lieux, Restaurants, Activités,
     * Services), chacun avec sa copie du classeur.
     *
     * @return list<FicheImportJob>
     */
    public function createFromExportUpload(UploadedFile $file, string $actor): array
    {
        if (!$file->isValid()) {
            throw new \DomainException('Le fichier téléversé est invalide.');
        }
        if ('xlsx' !== strtolower($file->getClientOriginalExtension())
            && !str_contains((string) $file->getMimeType(), 'spreadsheetml')
        ) {
            throw new \DomainException('L’import en masse attend un classeur XLSX issu de l’export du référentiel.');
        }

        $feuilles = self::feuilles($file->getPathname());
        $types = array_values(array_filter(
            FicheImportSchemaRegistry::supportedTypes(),
            static fn (TypeFiche $type): bool => in_array(FicheExportXlsxGenerator::nomFeuille($type), $feuilles, true),
        ));
        if ([] === $types) {
            throw new \DomainException('Aucune feuille de gamme reconnue (Lieux, Restaurants, Activités, Services) : utilisez un fichier issu de l’export du référentiel.');
        }

        if (!is_dir($this->importDir) && !@mkdir($this->importDir, 0775, true) && !is_dir($this->importDir)) {
            throw new \DomainException('Impossible de préparer le répertoire d’import.');
        }

        $jobs = [];
        foreach ($types as $type) {
            $job = new FicheImportJob($type, $file->getClientOriginalName(), $actor, 'xlsx', FicheImportJob::MODE_ECRASEMENT);
            if (!@copy($file->getPathname(), $this->importDir.'/'.$job->storagePath())) {
                throw new \DomainException('Impossible d’enregistrer le fichier téléversé.');
            }
            $this->entityManager->persist($job);
            $this->outbox->enqueue(new StartFicheImport($job->idString()));
            $jobs[] = $job;
        }
        $this->entityManager->flush();

        return $jobs;
    }

    /** @return list<string> noms des feuilles du classeur */
    private static function feuilles(string $path): array
    {
        $reader = new Reader();

        try {
            $reader->open($path);
        } catch (\Throwable) {
            throw new \DomainException('Classeur illisible.');
        }

        try {
            $noms = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                $noms[] = $sheet->getName();
            }

            return $noms;
        } finally {
            $reader->close();
        }
    }
}
