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
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

/**
 * Envoi Produits groupé : les fiches modifiées depuis leur dernier envoi
 * partent en paquets multi-lignes (une pièce jointe sous le plafond de
 * taille) — une modification de masse produit une poignée d'e-mails au lieu
 * d'un par fiche. La coalescence est portée par FicheSalesforceExport (une
 * rafale de mutations ne laisse qu'une ligne à traiter) ; le lot est borné
 * par tic, le reliquat part au tic suivant. Hydratation par sous-lots avec
 * clear() : mille fiches + lieux d'un coup feraient tomber le worker (512 Mo).
 */
#[WithMonologChannel('salesforce')]
#[AsMessageHandler]
final readonly class FlushSalesforceExportsHandler
{
    private const LOT = 1000;
    /** Fiches hydratées simultanément (avec lieu pour les Lieux). */
    private const SOUS_LOT = 200;

    public function __construct(
        private FicheSalesforceExportRepository $exports,
        private FicheRepository $fiches,
        private SalesforceProduitsCsvExporter $exporter,
        private SalesforceCsvMailer $mailer,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(FlushSalesforceExports $message): void
    {
        if (!$this->mailer->isConfigured()) {
            return;
        }
        // Échéances capturées avant l'envoi : une mutation concurrente pendant
        // l'e-mail repousse dirtyAt et sera renvoyée au tic suivant. Données
        // scalaires seulement — les entités ne survivent pas aux clear().
        $bornes = $this->exports->dirtyProduitsDonnees(self::LOT);
        if ([] === $bornes) {
            return;
        }
        $orphelins = [];
        foreach ($this->exporter->csvParPaquets($this->fichesParSousLots(array_keys($bornes), $orphelins)) as $paquet) {
            try {
                $this->mailer->envoyer(SalesforceCsvInterface::Produits, $paquet['csv']);
                foreach ($paquet['ficheIds'] as $ficheId) {
                    $this->exports->forFiche(Ulid::fromString($ficheId))?->recordProduitsSent($bornes[$ficheId]);
                }
            } catch (\Throwable $exception) {
                // L'échec est celui du transport, pas d'une fiche : tout le
                // paquet repart en backoff, le tic suivant sert d'autres
                // fiches grâce au filtre retryAt.
                foreach ($paquet['ficheIds'] as $ficheId) {
                    $this->exports->forFiche(Ulid::fromString($ficheId))?->recordFailure($exception->getMessage());
                }
                $this->logger->error('Envoi Salesforce Produits en échec pour un paquet de {nombre} fiche(s) : {message}', [
                    'nombre' => count($paquet['ficheIds']),
                    'message' => $exception->getMessage(),
                ]);
            }
            // Marquage flushé aussitôt après chaque paquet : un crash du
            // worker ne renvoie pas en double les e-mails déjà partis.
            $this->entityManager->flush();
            $this->entityManager->clear();
        }
        // Fiches supprimées depuis le marquage : leur suivi n'a plus d'objet.
        if ([] !== $orphelins) {
            foreach ($orphelins as $ficheId) {
                $export = $this->exports->forFiche(Ulid::fromString($ficheId));
                if (null !== $export) {
                    $this->entityManager->remove($export);
                }
            }
            $this->entityManager->flush();
        }
    }

    /**
     * @param list<string> $ficheIds
     * @param list<string> $orphelins rempli au fil de l'hydratation (fiches supprimées)
     *
     * @return iterable<Fiche>
     */
    private function fichesParSousLots(array $ficheIds, array &$orphelins): iterable
    {
        foreach (array_chunk($ficheIds, self::SOUS_LOT) as $chunk) {
            foreach ($chunk as $ficheId) {
                $fiche = $this->fiches->find(Ulid::fromString($ficheId));
                if ($fiche instanceof Fiche) {
                    yield $fiche;
                } else {
                    $orphelins[] = $ficheId;
                }
            }
            $this->entityManager->clear();
        }
    }
}
