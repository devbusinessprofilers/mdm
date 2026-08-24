<?php

declare(strict_types=1);

namespace App\Etl\MessageHandler;

use App\Etl\Enum\SalesforceCsvInterface;
use App\Etl\Message\FlushSalesforceExports;
use App\Etl\Repository\FicheSalesforceExportRepository;
use App\Etl\Service\SalesforceCsvMailer;
use App\Etl\Service\SalesforceProduitsCsvExporter;
use App\Pim\Entity\Fiche;
use App\Pim\Repository\FicheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Envoie un e-mail Produits par fiche modifiée depuis son dernier envoi. La
 * coalescence est portée par FicheSalesforceExport (une rafale de mutations ne
 * laisse qu'une ligne à traiter). Traite un lot borné par tic ; le reliquat
 * part au tic suivant.
 */
#[AsMessageHandler]
final readonly class FlushSalesforceExportsHandler
{
    private const LOT = 200;

    public function __construct(
        private FicheSalesforceExportRepository $exports,
        private FicheRepository $fiches,
        private SalesforceProduitsCsvExporter $exporter,
        private SalesforceCsvMailer $mailer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(FlushSalesforceExports $message): void
    {
        if (!$this->mailer->isConfigured()) {
            return;
        }
        foreach ($this->exports->dirtyProduits(self::LOT) as $export) {
            $fiche = $this->fiches->find($export->ficheId());
            if (!$fiche instanceof Fiche) {
                // Fiche supprimée : plus rien à synchroniser.
                $this->entityManager->remove($export);
                continue;
            }
            // Échéance capturée avant l'envoi : une mutation concurrente
            // pendant l'e-mail repousse dirtyAt et sera renvoyée au tic suivant.
            $borne = $export->dirtyAt();
            try {
                $this->mailer->envoyer(SalesforceCsvInterface::Produits, $this->exporter->csv([$fiche]));
                $export->recordProduitsSent($borne);
            } catch (\Throwable $exception) {
                $export->recordFailure($exception->getMessage());
            }
        }
        $this->entityManager->flush();
    }
}
