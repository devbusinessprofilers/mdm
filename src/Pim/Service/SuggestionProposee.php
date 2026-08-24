<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Enum\SuggestionAction;

/**
 * Constat de vérification prêt à devenir (ou rafraîchir) une FicheSuggestion :
 * une action, le champ visé, et les valeurs à présenter à l'arbitre.
 */
final readonly class SuggestionProposee
{
    /** @param array<string, mixed>|null $payload données machine pour l'application (codes LOV, booléen) */
    public function __construct(
        public SuggestionAction $action,
        public string $champ,
        public string $label,
        public ?string $valeurActuelle,
        public ?string $valeurProposee,
        public ?float $score,
        public ?array $payload = null,
    ) {}
}
