<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheSuggestion;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\FicheSuggestionRepository;
use App\Pim\Service\AdresseSuggestionArbitre;
use App\Pim\Service\EnrichissementSuggestionArbitre;
use Symfony\Component\Uid\Ulid;

/**
 * Arbitrage groupé des lignes cochées du tableau de suggestions Qualité. Chaque
 * identifiant porte sa famille : « adresse:<ficheId> » (suggestion BAN/Geoapify
 * sur la localisation) ou « suggestion:<id> » (FicheSuggestion générique). Une
 * ligne en échec (déjà arbitrée, sans proposition…) est comptée à part sans
 * interrompre le lot.
 */
final readonly class SuggestionsBulkArbitre
{
    public function __construct(
        private FicheRepository $fiches,
        private FicheSuggestionRepository $suggestions,
        private AdresseSuggestionArbitre $adresses,
        private EnrichissementSuggestionArbitre $enrichissements,
    ) {
    }

    /**
     * @param list<string> $selectIds
     *
     * @return array{ok: int, echecs: int}
     */
    public function arbitrer(array $selectIds, string $decision, string $actor): array
    {
        $accepter = 'accepter' === $decision;
        $ok = 0;
        $echecs = 0;
        foreach ($selectIds as $selectId) {
            try {
                if (str_starts_with($selectId, 'adresse:')) {
                    $this->arbitrerAdresse(substr($selectId, 8), $accepter);
                } elseif (str_starts_with($selectId, 'suggestion:')) {
                    $this->arbitrerGenerique(substr($selectId, 11), $accepter, $actor);
                } else {
                    ++$echecs;

                    continue;
                }
                ++$ok;
            } catch (\Throwable) {
                ++$echecs;
            }
        }

        return ['ok' => $ok, 'echecs' => $echecs];
    }

    private function arbitrerAdresse(string $ficheId, bool $accepter): void
    {
        $fiche = $this->fiches->find(Ulid::fromString($ficheId));
        if (!$fiche instanceof Fiche) {
            throw new \DomainException('Fiche introuvable.');
        }
        $accepter ? $this->adresses->accepter($fiche) : $this->adresses->ignorer($fiche);
    }

    private function arbitrerGenerique(string $id, bool $accepter, string $actor): void
    {
        $suggestion = $this->suggestions->find(Ulid::fromString($id));
        if (!$suggestion instanceof FicheSuggestion) {
            throw new \DomainException('Suggestion introuvable.');
        }
        $accepter ? $this->enrichissements->accepter($suggestion, $actor) : $this->enrichissements->ignorer($suggestion, $actor);
    }
}
