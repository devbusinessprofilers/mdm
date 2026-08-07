<?php

declare(strict_types=1);

namespace App\Audit\Restore;

use App\Audit\Entity\AuditChange;
use App\Audit\Entity\AuditRevision;
use App\Enrichment\Service\FicheTranslationScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Message\IndexFiche;
use App\Pim\Service\InternalFicheMutationPolicy;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AuditRestorer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RestorableFieldCatalog $catalog,
        private InternalFicheMutationPolicy $mutationPolicy,
        private FicheTranslationScheduler $translationScheduler,
        private OutboxPublisherInterface $outbox,
    ) {
    }

    public function restoreChange(
        AuditChange $change,
        int $expectedVersion,
    ): Fiche {
        $fiche = $this->fiche($change->revision(), $expectedVersion);
        $this->mutationPolicy->execute(
            $fiche,
            fn () => $this->catalog->restore(
                $fiche,
                $change->path(),
                $change->oldValue(),
            ),
        );
        $this->finalize($fiche);

        return $fiche;
    }

    /**
     * Applique les oldValue de tous les chemins restaurables de la révision
     * en un seul flush (= une seule révision d'audit résultante).
     *
     * @return list<string> chemins effectivement appliqués
     */
    public function restoreRevision(
        AuditRevision $revision,
        int $expectedVersion,
    ): array {
        $fiche = $this->fiche($revision, $expectedVersion);
        $applied = [];
        $this->mutationPolicy->execute($fiche, function () use (
            $revision,
            $fiche,
            &$applied,
        ): void {
            foreach ($revision->changes() as $change) {
                if (!$this->catalog->isRestorable($change->path())) {
                    continue;
                }
                $this->catalog->restore(
                    $fiche,
                    $change->path(),
                    $change->oldValue(),
                );
                $applied[] = $change->path();
            }
        });
        if ([] === $applied) {
            throw new NotRestorableException(
                'Aucun champ de cette révision n\'est restaurable.',
            );
        }
        $this->finalize($fiche);

        return $applied;
    }

    private function fiche(
        AuditRevision $revision,
        int $expectedVersion,
    ): Fiche {
        $fiche = $this->entityManager->find(
            Fiche::class,
            $revision->ficheId(),
        );
        if (!$fiche instanceof Fiche) {
            throw new NotRestorableException('Fiche introuvable.');
        }
        if ($fiche->version() !== $expectedVersion) {
            throw new StaleVersionException(
                'La fiche a été modifiée depuis l\'affichage de l\'historique.',
            );
        }

        return $fiche;
    }

    private function finalize(Fiche $fiche): void
    {
        $this->translationScheduler->schedule($fiche);
        $this->outbox->enqueue(new IndexFiche($fiche->idString()));
        $this->entityManager->flush();
    }
}
