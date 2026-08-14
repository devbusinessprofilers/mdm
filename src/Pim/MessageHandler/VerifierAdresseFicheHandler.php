<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Entity\Fiche;
use App\Pim\Message\IndexFiche;
use App\Pim\Message\VerifierAdresseFiche;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\GeocodeurAdresses;
use App\Pim\Service\LocalisationBanVerifier;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

/**
 * Vérifie l'adresse d'une fiche au fil de l'eau — BAN pour la France,
 * Geoapify pour l'étranger : mêmes règles que la commande batch
 * (enrichissements sûrs, trace, écart pour l'arbitrage Qualité), aucune
 * transition de workflow.
 */
#[AsMessageHandler]
final readonly class VerifierAdresseFicheHandler
{
    public function __construct(
        private FicheRepository $fiches,
        private GeocodeurAdresses $geocodeurs,
        private OutboxPublisherInterface $outbox,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(VerifierAdresseFiche $message): void
    {
        $fiche = $this->fiches->find(Ulid::fromString($message->ficheId));
        if (!$fiche instanceof Fiche) {
            return;
        }
        $localisation = $fiche->localisation();
        $ligne = $this->geocodeurs->ligne($fiche);
        if (null === $localisation || null === $ligne) {
            return;
        }
        $client = $this->geocodeurs->clientPour($localisation);
        if (null === $client) {
            return;
        }
        // L'adresse a pu être revérifiée entre l'enfilement et le traitement.
        if (null !== $localisation->banFingerprint()
            && $localisation->banFingerprint() === $localisation->addressFingerprint()) {
            return;
        }
        $resultat = $client->verifierLot([$ligne])[$ligne['id']] ?? null;
        $modifie = LocalisationBanVerifier::appliquerEnrichissements($localisation, $resultat, LocalisationBanVerifier::SEUIL_DEFAUT);
        // La trace capture l'empreinte APRÈS enrichissement : le re-passage
        // par IndexFiche ne redéclenche donc pas de vérification (pas de
        // boucle), il ne fait que réindexer et resynchroniser.
        LocalisationBanVerifier::tracer($localisation, $resultat, LocalisationBanVerifier::SEUIL_DEFAUT);
        if ($modifie) {
            $this->outbox->enqueue(new IndexFiche($fiche->idString()));
        }
        $this->entityManager->flush();
        $this->logger->info('Adresse vérifiée.', [
            'fiche' => $fiche->idString(),
            'panier' => LocalisationBanVerifier::panier($localisation, $resultat, LocalisationBanVerifier::SEUIL_DEFAUT),
            'score' => $resultat['score'] ?? null,
        ]);
    }
}
