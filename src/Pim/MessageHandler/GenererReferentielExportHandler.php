<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Entity\ReferentielExport;
use App\Pim\Export\ReferentielExporteur;
use App\Pim\Form\ReferentielFiltres;
use App\Pim\Message\GenererReferentielExport;
use App\Pim\Repository\ReferentielExportRepository;
use App\Pim\Service\ReferentielListeProvider;
use App\Shared\Service\PrivateObjectStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

/**
 * Génère le classeur d'un export du référentiel en tâche de fond : la page
 * de suivi (et le journal /outils) suivent le statut, le fichier attend son
 * téléchargement sur le bucket privé OVH (rétention 30 jours, purge par
 * app:referentiel:purger-exports).
 */
#[AsMessageHandler]
final readonly class GenererReferentielExportHandler
{
    public const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(
        private ReferentielExportRepository $exports,
        private ReferentielListeProvider $provider,
        private ReferentielExporteur $exporteur,
        private PrivateObjectStorageInterface $storage,
        private EntityManagerInterface $entityManager,
        #[Autowire('%env(S3_PREFIX)%')] private string $storagePrefix,
    ) {
    }

    /** Clé du classeur sur le bucket privé, sous le préfixe d'environnement. */
    public static function cle(string $storagePrefix, string $exportId): string
    {
        $prefix = trim($storagePrefix, '/');

        return ('' === $prefix ? '' : $prefix.'/').'exports/'.$exportId.'.xlsx';
    }

    public function __invoke(GenererReferentielExport $message): void
    {
        $export = $this->exports->find(Ulid::fromString($message->exportId));
        if (!$export instanceof ReferentielExport || !$export->enAttente()) {
            return; // relivraison d'un message déjà traité
        }
        $export->demarrer();
        // Flush voulu : l'exporteur vide l'EntityManager entre ses lots, le passage « en cours » serait perdu.
        $this->entityManager->flush();

        try {
            $ids = $export->ids()
                ?? $this->provider->idsPourFiltre(ReferentielFiltres::fromArray($export->filtres()), PHP_INT_MAX);
            $temporaire = $this->exporteur->exporter($ids, $export->colonnes());

            $stream = fopen($temporaire, 'rb');
            if (false === $stream) {
                throw new \RuntimeException('Impossible de relire le classeur généré.');
            }
            try {
                $this->storage->writeStream(
                    self::cle($this->storagePrefix, $message->exportId),
                    $stream,
                    ['ContentType' => self::CONTENT_TYPE],
                );
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                @unlink($temporaire);
            }

            // L'export purge l'EntityManager entre ses lots : l'entité se recharge.
            $this->conclure($message->exportId, static fn (ReferentielExport $export) => $export->terminer(count($ids)));
        } catch (\Throwable $exception) {
            // L'échec vit dans l'entité (journal /outils) : pas de relivraison.
            $this->conclure($message->exportId, static fn (ReferentielExport $export) => $export->echouer($exception->getMessage()));
        }
    }

    /** @param callable(ReferentielExport): void $transition */
    private function conclure(string $exportId, callable $transition): void
    {
        $export = $this->exports->find(Ulid::fromString($exportId));
        if ($export instanceof ReferentielExport) {
            $transition($export);
        }
    }
}
