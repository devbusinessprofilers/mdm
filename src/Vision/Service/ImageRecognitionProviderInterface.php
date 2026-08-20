<?php

declare(strict_types=1);

namespace App\Vision\Service;

interface ImageRecognitionProviderInterface
{
    public function describe(string $imageUrl, string $prompt, string $model): RecognitionResult;
}
