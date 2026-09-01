<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\SuggestionAction;
use App\Shared\Service\ParametreProviderInterface;
use App\Vision\Service\OpenAiProviderException;

/**
 * Propose par IA les atouts d'une fiche qui n'en a aucun, à partir de sa
 * description générale — sans description, rien n'est proposé : l'IA reformule
 * de la matière existante, elle n'invente pas. Une seule suggestion (source
 * « IA ») portant la liste complète en payload, arbitrée comme les autres.
 * La réponse est re-validée ligne à ligne : puces et numérotations retirées,
 * atouts trop longs écartés (jamais tronqués), liste plafonnée.
 *
 * Gardé par openai.actif ; déclenché par le bouton « Enrichir ce qui manque ».
 */
final readonly class AtoutsIaVerifier
{
    public function __construct(
        private ChampSuggestionService $suggestions,
        private ParametreProviderInterface $parametres,
    ) {
    }

    /**
     * @param string       $champ         clé arbitrable de la gamme (lieu_atouts, restaurant_atouts, activite_plus)
     * @param list<string> $atoutsActuels atouts déjà saisis — non vide = rien à proposer (backfill strict)
     *
     * @return list<SuggestionProposee>
     *
     * @throws EnrichissementIndisponibleException
     */
    public function analyser(Fiche $fiche, ?string $description, string $champ, array $atoutsActuels, int $max, int $longueurMax): array
    {
        if (!$this->parametres->bool('openai.actif') || [] !== $atoutsActuels) {
            return [];
        }
        $description = trim(strip_tags((string) $description));
        if ('' === $description) {
            return [];
        }
        try {
            $reponse = $this->suggestions->suggererAtouts($fiche, $description, $max, $longueurMax);
        } catch (OpenAiProviderException $exception) {
            if ($exception->retryable) {
                throw new EnrichissementIndisponibleException($exception->getMessage(), 0, $exception);
            }

            return [];
        }
        $atouts = self::atouts($reponse, $max, $longueurMax);
        if ([] === $atouts) {
            return [];
        }

        return [new SuggestionProposee(
            action: SuggestionAction::RemplirChamp,
            champ: $champ,
            label: 'Atouts',
            valeurActuelle: null,
            valeurProposee: implode(' · ', $atouts),
            score: null,
            payload: ['liste' => $atouts],
        )];
    }

    /** @return list<string> */
    private static function atouts(string $reponse, int $max, int $longueurMax): array
    {
        $atouts = [];
        foreach (preg_split('/\R/u', $reponse) ?: [] as $ligne) {
            $atout = trim((string) preg_replace('/^[\s\-–—•*\d.)]+/u', '', trim($ligne)));
            if ('' === $atout || mb_strlen($atout) > $longueurMax || in_array($atout, $atouts, true)) {
                continue;
            }
            $atouts[] = $atout;
        }

        return array_slice($atouts, 0, $max);
    }
}
