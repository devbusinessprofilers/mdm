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
use Symfony\Component\Uid\Ulid;

/**
 * Envoi nocturne groupé des salles : un unique e-mail « Salles » (CSV
 * multi-lignes) pour toutes les fiches modifiées depuis leur dernier envoi de
 * salles. Les fiches sans salle sont marquées envoyées pour ne pas être
 * reprises chaque nuit. Le lot est traité par sous-lots avec clear() entre
 * chacun : jusqu'à 5 000 fiches + lieux + salles hydratés d'un coup feraient
 * tomber le worker (512 Mo).
 */
#[AsMessageHandler]
final readonly class SendSalesforceSallesBatchHandler
{
    private const LOT = 5000;
    /** Fiches hydratées simultanément (avec lieu + salles). */
    private const SOUS_LOT = 200;

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
        $lignes = $this->exports->dirtySallesDonnees(self::LOT);
        if ([] === $lignes) {
            return;
        }
        // Tri des lignes en retard : seules des données scalaires (id, échéance
        // dirtyAt capturée avant l'envoi — une mutation concurrente repousse
        // dirtyAt et repart la nuit suivante) survivent aux clear().
        $avecSalles = [];
        $sansSalles = [];
        $orphelins = [];
        foreach (array_chunk($lignes, self::SOUS_LOT, preserve_keys: true) as $chunk) {
            foreach ($chunk as $ficheId => $borne) {
                $fiche = $this->fiches->parId($ficheId);
                if (!$fiche instanceof Fiche) {
                    $orphelins[] = $ficheId;
                } elseif ($this->exporter->possedeDesSalles($fiche)) {
                    $avecSalles[$ficheId] = $borne;
                } else {
                    // Marquée envoyée même sans salle : rien à transmettre, on
                    // n'y revient pas chaque nuit.
                    $sansSalles[$ficheId] = $borne;
                }
            }
            $this->entityManager->clear();
        }
        $this->solder($sansSalles, $orphelins);
        if ([] === $avecSalles) {
            return;
        }
        $this->mailer->envoyer(SalesforceCsvInterface::Salles, $this->exporter->csv($this->fichesParSousLots(array_keys($avecSalles))));
        // Marquage flushé aussitôt après l'e-mail : borne la fenêtre de renvoi
        // en double si le worker meurt ensuite.
        $this->solder($avecSalles, []);
    }

    /**
     * @param array<string, \DateTimeImmutable> $bornes
     * @param list<string>                      $orphelins fiches supprimées : leur suivi n'a plus d'objet
     */
    private function solder(array $bornes, array $orphelins): void
    {
        foreach ($orphelins as $ficheId) {
            $export = $this->exports->forFiche(Ulid::fromString($ficheId));
            if (null !== $export) {
                $this->entityManager->remove($export);
            }
        }
        foreach ($bornes as $ficheId => $borne) {
            $this->exports->forFiche(Ulid::fromString($ficheId))?->recordSallesSent($borne);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @param list<string> $ficheIds
     *
     * @return iterable<Fiche>
     */
    private function fichesParSousLots(array $ficheIds): iterable
    {
        foreach (array_chunk($ficheIds, self::SOUS_LOT) as $chunk) {
            foreach ($chunk as $ficheId) {
                $fiche = $this->fiches->parId($ficheId);
                if ($fiche instanceof Fiche) {
                    yield $fiche;
                }
            }
            $this->entityManager->clear();
        }
    }
}
