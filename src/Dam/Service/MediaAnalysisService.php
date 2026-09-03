<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Entity\MediaDuplicateAlert;
use App\Dam\Enum\DuplicateKind;
use App\Dam\Enum\DuplicateReviewStatus;
use App\Dam\Enum\MediaKind;
use App\Dam\Enum\MediaStatus;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Repository\MediaDuplicateAlertRepository;
use App\Dam\Repository\MediaPerceptualHashBandRepository;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Service\ParametreProviderInterface;
use App\Shared\Service\PrivateObjectStorageInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MediaAnalysisService
{
    public function __construct(
        private PrivateObjectStorageInterface $storage,
        private PerceptualHashCalculator $calculator,
        private MediaAssetRepository $assets,
        private MediaDuplicateAlertRepository $alerts,
        private MediaPerceptualHashBandRepository $bands,
        private RessourceLieuRepository $resources,
        private EntityManagerInterface $entityManager,
        private ParametreProviderInterface $parametres,
    ) {
    }

    /**
     * @param string|null $originalPath copie locale de l'original, pour éviter
     *                                  un nouveau téléchargement S3 quand
     *                                  l'appelant l'a déjà récupéré
     */
    public function analyze(MediaAsset $media, ?string $originalPath = null): ?MediaAsset
    {
        if (MediaKind::Image !== $media->kind() || in_array($media->status(), [MediaStatus::Deleting, MediaStatus::Deleted], true)) {
            return null;
        }
        $hash = $media->perceptualHash();
        if (null === $hash) {
            $stream = null === $originalPath
                ? $this->storage->readStream($media->originalStorageKey())
                : fopen($originalPath, 'rb');
            if (false === $stream) {
                throw new \RuntimeException("Impossible de lire la copie locale de l'original.");
            }
            try {
                $hash = $this->calculator->calculate($stream);
            } finally {
                fclose($stream);
            }
            $media->recordPerceptualHash($hash);
        }

        $duplicate = $this->assets->findOldestActiveImageByChecksum($media->checksum(), $media->id());
        $kind = null;
        $distance = null;
        if (null !== $duplicate) {
            $kind = DuplicateKind::Exact;
            $distance = 0;
        } else {
            [$duplicate, $distance] = $this->nearestPerceptualDuplicate($media, $hash);
            $kind = null === $duplicate ? null : DuplicateKind::Perceptual;
        }

        $alert = $this->alerts->findForMedia($media);
        if (null !== $duplicate && null !== $kind) {
            if (null === $alert) {
                $resource = $this->resources->findOneByMediaId($media->id());
                if (null === $resource) {
                    $this->entityManager->flush();
                    $this->bands->replace($media->id(), $hash);

                    return $duplicate;
                }
                $alert = new MediaDuplicateAlert($media, $duplicate, $resource, $kind, $distance);
                $this->entityManager->persist($alert);
            } else {
                $alert->refresh($duplicate, $kind, $distance);
            }
        } elseif (null !== $alert && DuplicateReviewStatus::Pending === $alert->status()) {
            $alert->resolve('system');
        }

        $this->entityManager->flush();
        $this->bands->replace($media->id(), $hash);

        return $duplicate;
    }

    /** @return array{MediaAsset|null, int|null} */
    private function nearestPerceptualDuplicate(MediaAsset $media, string $hash): array
    {
        $best = null;
        $distanceThreshold = $this->parametres->int('dam.seuil_distance_phash');
        $bestDistance = $distanceThreshold + 1;
        foreach ($this->assets->findActiveImagesByStringIds($this->bands->candidateIds($hash, $media->id())) as $candidate) {
            $candidateHash = $candidate->perceptualHash();
            if (null === $candidateHash) {
                continue;
            }
            $distance = $this->calculator->distance($hash, $candidateHash);
            if ($distance > $distanceThreshold) {
                continue;
            }
            if (null === $best || $distance < $bestDistance || ($distance === $bestDistance && [$candidate->createdAt(), $candidate->id()] < [$best->createdAt(), $best->id()])) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return [null === $best ? null : $best, null === $best ? null : $bestDistance];
    }
}
