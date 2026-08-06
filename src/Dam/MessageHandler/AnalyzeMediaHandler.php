<?php

declare(strict_types=1);

namespace App\Dam\MessageHandler;

use App\Dam\Message\AnalyzeMedia;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\MediaAnalysisService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AnalyzeMediaHandler
{
    public function __construct(
        private MediaAssetRepository $assets,
        private MediaAnalysisService $analysis,
    ) {}

    public function __invoke(AnalyzeMedia $message): void
    {
        $media = $this->assets->find($message->mediaId);
        if (null === $media) {
            return;
        }
        $this->analysis->analyze($media);
    }
}
