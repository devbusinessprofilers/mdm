<?php

declare(strict_types=1);

namespace App\Vision\Service;

/**
 * Suggestion de texte par l'IA : un prompt en entrée, une proposition rédigée
 * en sortie. Sert la pilule « Suggérer » des champs de description de la fiche.
 */
interface TextSuggestionProviderInterface
{
    /**
     * @throws OpenAiProviderException
     */
    public function suggerer(string $prompt, string $model): string;
}
