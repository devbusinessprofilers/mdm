<?php

declare(strict_types=1);

namespace App\Vision\Service;

interface ImageEnhancementProviderInterface
{
    public function enhance(string $imagePath, string $mimeType, string $prompt, string $model): EnhancedImageResult;
}
