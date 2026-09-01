<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Shared\Service\ParametreProviderInterface;
use App\Vision\Service\OpenAiProviderException;
use App\Vision\Service\TextSuggestionProviderInterface;

/**
 * Suggestion IA d'un champ de description de la fiche : assemble le contexte de
 * la fiche (nom, gamme, ville) et le contenu actuel du champ dans le gabarit de
 * prompt paramétré, puis délègue l'appel au fournisseur OpenAI. Les paramètres
 * sont lus à l'usage pour honorer une surcharge faite dans /admin.
 */
final readonly class ChampSuggestionService
{
    /** Garde-fou : contenu tronqué avant l'appel (les champs sont déjà bornés à la saisie). */
    private const VALEUR_MAX = 8000;

    public function __construct(
        private TextSuggestionProviderInterface $provider,
        private ParametreProviderInterface $parametres,
    ) {
    }

    /**
     * @throws OpenAiProviderException
     */
    public function suggerer(Fiche $fiche, string $champ, string $valeurActuelle): string
    {
        if (!$this->parametres->bool('openai.actif')) {
            throw new OpenAiProviderException('Suggestion IA désactivée.', false);
        }
        $valeur = mb_substr(trim($valeurActuelle), 0, self::VALEUR_MAX);
        $prompt = strtr($this->parametres->string('openai.suggestion_prompt'), [
            '{contexte}' => $this->contexte($fiche),
            '{champ}' => '' !== trim($champ) ? trim($champ) : 'Description',
            '{valeur}' => '' !== $valeur ? $valeur : '(champ vide)',
        ]);

        return $this->provider->suggerer($prompt, $this->parametres->string('openai.suggestion_modele'));
    }

    /**
     * Suggestion des atouts d'une fiche à partir de sa description (la seule
     * matière première fiable) : réponse attendue en un atout par ligne, que
     * l'appelant re-valide.
     *
     * @throws OpenAiProviderException
     */
    public function suggererAtouts(Fiche $fiche, string $description, int $max, int $longueurMax): string
    {
        if (!$this->parametres->bool('openai.actif')) {
            throw new OpenAiProviderException('Suggestion IA désactivée.', false);
        }
        $prompt = strtr($this->parametres->string('openai.atouts_prompt'), [
            '{contexte}' => $this->contexte($fiche),
            '{description}' => mb_substr(trim($description), 0, self::VALEUR_MAX),
            '{max}' => (string) $max,
            '{longueur_max}' => (string) $longueurMax,
        ]);

        return $this->provider->suggerer($prompt, $this->parametres->string('openai.suggestion_modele'));
    }

    private function contexte(Fiche $fiche): string
    {
        $elements = array_filter([
            'Nom' => $fiche->label(),
            'Gamme' => $fiche->type()->value,
            'Ville' => $fiche->localisation()?->ville(),
        ], static fn (?string $v): bool => null !== $v && '' !== trim($v));

        $lignes = [];
        foreach ($elements as $cle => $valeur) {
            $lignes[] = $cle.' : '.trim((string) $valeur);
        }

        return [] === $lignes ? 'Fiche sans contexte renseigné.' : implode("\n", $lignes);
    }
}
