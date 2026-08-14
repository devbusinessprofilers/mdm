<?php

declare(strict_types=1);

namespace App\Ocr\Message;

/**
 * Application automatique des suggestions à haute confiance d'une
 * extraction terminée (paramètre ocr.seuil_application_auto, 0 = manuel).
 */
final readonly class AutoApplyOcrSuggestions
{
    public function __construct(public string $extractionId)
    {
    }
}
