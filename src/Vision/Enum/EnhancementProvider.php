<?php

declare(strict_types=1);

namespace App\Vision\Enum;

/**
 * Moteur d'une retouche : génératif OpenAI (images/edits) ou correction
 * locale déterministe ImageMagick. Les deux partagent le même cycle de vie
 * (candidate privée, avant/après, acceptation) mais pas les mêmes gardes :
 * seul OpenAI dépend de openai.actif.
 */
enum EnhancementProvider: string
{
    case OpenAi = 'openai';
    case ImageMagick = 'imagemagick';
}
