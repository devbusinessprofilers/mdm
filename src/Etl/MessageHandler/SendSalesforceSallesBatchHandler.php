<?php

declare(strict_types=1);

namespace App\Etl\MessageHandler;

use App\Etl\Enum\SalesforceCsvInterface;
use App\Etl\Message\SendSalesforceSallesBatch;
use App\Etl\Repository\FicheSalesforceExportRepository;
use App\Etl\Service\SalesforceCsvMailer;
use App\Etl\Service\SalesforceSallesCsvExporter;
use App\Pim\Entity\Fiche;
use App\Pim\Repository\FicheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Envoi nocturne groupé des salles : un unique e-mail « Salles » (CSV
 * multi-lignes) pour toutes les fiches modifiées depuis leur dernier envoi de
 * salles. Les fiches sans salle sont marquées envoyées pour ne pas être
 * reprises chaque nuit.
 */
#[AsMessageHandler]
final readonly class SendSalesforceSallesBatchHandler
{
    private const LOT = 5000;

    public function __construct(
        private FicheSalesforceExportRepository $exports,
        private FicheRepository $fiches,
        private SalesforceSallesCsvExporter $exporter,
        private SalesforceCsvMailer $mailer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SendSalesforceSallesBatch $message): void
    {
        if (!$this->mailer->isConfigured()) {
            return;
        }
        /** @var list<Fiche> $avecSalles */
        $avecSalles = [];
        foreach ($this->exports->dirtySalles(self::LOT) as $export) {
            $fiche = $this->fiches->find($export->ficheId());
            if (!$fiche instanceof Fiche) {
                $this->entityManager->remove($export);
                continue;
            }
            if ($this->exporter->possedeDesSalles($fiche)) {
                $avecSalles[] = $fiche;
            }
            // Marqué envoyé même sans salle : rien à transmettre, on n'y revient
            // pas. Échéance capturée avant l'envoi groupé : une mutation
            // concurrente repousse dirtyAt et repart la nuit suivante.
            $export->recordSallesSent($export->dirtyAt());
        }
        if ([] !== $avecSalles) {
            $this->mailer->envoyer(SalesforceCsvInterface::Salles, $this->exporter->csv($avecSalles));
        }
        $this->entityManager->flush();
    }
}
