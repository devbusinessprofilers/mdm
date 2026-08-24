<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Etl\Entity\FicheSalesforceExport;
use App\Etl\Repository\FicheSalesforceExportRepository;
use App\Pim\Entity\Fiche;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Marque une fiche « à synchroniser » vers Salesforce à chaque mutation (point
 * de convergence IndexFicheHandler). L'écriture technique se fait dans la
 * transaction du flush de l'indexeur ; l'envoi effectif est différé (Produits
 * au fil de l'eau via FlushSalesforceExports, Salles la nuit).
 *
 * Calqué sur MarketplaceSyncScheduler. L'import legacy n'enfile jamais
 * IndexFiche : il ne passe donc pas par ce planificateur. La garde
 * isConfigured() neutralise en plus la synchro tant qu'elle n'est pas activée.
 */
final readonly class SalesforceExportScheduler
{
    public function __construct(
        private FicheSalesforceExportRepository $exports,
        private SalesforceCsvSettings $settings,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function schedule(Fiche $fiche): void
    {
        if (!$this->settings->isConfigured()) {
            return;
        }
        $export = $this->exports->forFiche($fiche->id());
        if (null === $export) {
            // Le constructeur pose dirtyAt = maintenant.
            $this->entityManager->persist(new FicheSalesforceExport($fiche->id(), $fiche->code()));

            return;
        }
        $export->markDirty();
    }
}
