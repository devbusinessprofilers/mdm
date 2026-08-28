<?php

declare(strict_types=1);

namespace App\Enrichment\MessageHandler;

use App\Etl\Service\MarketplaceSyncScheduler;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Message\TranslatePublishedFiche;
use App\Enrichment\Repository\FicheTranslationRepository;
use App\Enrichment\Service\TranslationProviderInterface;
use App\Pim\Entity\Fiche;
use App\Pim\Repository\FicheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler]
final readonly class TranslatePublishedFicheHandler
{
    public function __construct(
        private FicheRepository $fiches,
        private FicheTranslationRepository $translations,
        private TranslationProviderInterface $provider,
        private EntityManagerInterface $entityManager,
        private MarketplaceSyncScheduler $marketplaceScheduler,
    ) {}

    public function __invoke(TranslatePublishedFiche $message): void
    {
        $fiche = $this->fiches->find(Ulid::fromString($message->ficheId));
        $locale = SupportedLocale::from($message->locale);
        // Pas de garde sur le statut : la relance manuelle traduit aussi les
        // fiches non publiées, et seules des lignes déjà planifiées (jeton
        // valide) sont traitées ici.
        if (!$fiche instanceof Fiche) { return; }
        $rows = $this->translations->requested($fiche, $locale, $message->requestToken);
        if ([] === $rows) { return; }
        $translated = $this->provider->translate(
            array_map(static fn ($row): string => $row->sourceText(), $rows),
            $locale,
        );
        foreach ($rows as $index => $row) { $row->applyGoogle($translated[$index], $message->requestToken); }
        // Les traductions fraîchement disponibles redescendent vers la
        // marketplace via une resynchronisation du snapshot complet.
        $this->marketplaceScheduler->schedule($fiche);
        $this->entityManager->flush();
    }
}
