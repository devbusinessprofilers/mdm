<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\SuggestionAction;
use App\Shared\Service\ParametreProviderInterface;
use App\Vision\Service\OpenAiProviderException;

/**
 * Propose par IA la description générale d'une fiche qui n'en a pas : une
 * suggestion d'enrichissement classique (source « IA »), arbitrée dans le bloc
 * « Suggestions en attente » comme celles de DATAtourisme. Rien n'est proposé
 * sur un champ déjà rempli : l'IA comble, elle ne réécrit pas (la réécriture
 * assistée reste la pilule « Suggérer » du champ).
 *
 * Gardé par openai.actif ; déclenché par le bouton « Enrichir ce qui manque ».
 */
final readonly class DescriptionIaVerifier
{
    /** Aperçu affiché dans la colonne « Proposé » (aligné sur DATAtourisme), texte complet en payload. */
    private const APERCU = 200;

    public function __construct(
        private ChampSuggestionService $suggestions,
        private ParametreProviderInterface $parametres,
    ) {
    }

    /**
     * @param string $champ clé de champ arbitrable de la gamme (ex. lieu_desc_generale)
     *
     * @return list<SuggestionProposee>
     *
     * @throws EnrichissementIndisponibleException
     */
    public function analyser(Fiche $fiche, ?string $descriptionActuelle, string $champ): array
    {
        if (!$this->parametres->bool('openai.actif')) {
            return [];
        }
        if (null !== $descriptionActuelle && '' !== trim($descriptionActuelle)) {
            return [];
        }
        try {
            $texte = trim($this->suggestions->suggerer($fiche, 'Description générale', ''));
        } catch (OpenAiProviderException $exception) {
            if ($exception->retryable) {
                throw new EnrichissementIndisponibleException($exception->getMessage(), 0, $exception);
            }

            return [];
        }
        if ('' === $texte) {
            return [];
        }
        $apercu = mb_substr($texte, 0, self::APERCU);

        return [new SuggestionProposee(
            action: SuggestionAction::RemplirChamp,
            champ: $champ,
            label: 'Description générale',
            valeurActuelle: null,
            valeurProposee: mb_strlen($texte) > self::APERCU ? $apercu.'…' : $apercu,
            score: null,
            payload: ['text' => $texte],
        )];
    }
}
