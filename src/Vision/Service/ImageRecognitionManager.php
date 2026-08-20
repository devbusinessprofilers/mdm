<?php

declare(strict_types=1);

namespace App\Vision\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Enum\MediaKind;
use App\Dam\Enum\MediaStatus;
use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Service\ParametreProviderInterface;
use App\Vision\Entity\ImageRecognition;
use App\Vision\Message\RecognizeImage;
use App\Vision\Repository\ImageRecognitionRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ImageRecognitionManager
{
    public function __construct(
        private ImageRecognitionRepository $recognitions,
        private RessourceLieuRepository $resources,
        private MediaAssetRepository $mediaRepository,
        private ParametreProviderInterface $parametres,
        private OutboxPublisherInterface $outbox,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Lancement manuel : une reconnaissance par photo exploitable des fiches
     * sélectionnées.
     *
     * @param list<Fiche> $fiches
     *
     * @return int nombre de reconnaissances lancées
     */
    public function launchForFiches(array $fiches, string $actor): int
    {
        if (!$this->parametres->bool('openai.actif')) {
            throw new \DomainException('La reconnaissance IA est désactivée (OPENAI_ENABLED).');
        }
        $launched = 0;
        foreach ($fiches as $fiche) {
            $photos = $this->resources->findBy(['fiche' => $fiche, 'nature' => NatureRessource::Photo]);
            foreach ($photos as $resource) {
                $media = '' === $resource->damAssetId() ? null : $this->mediaRepository->find($resource->damAssetId());
                if (null === $media || MediaKind::Image !== $media->kind() || MediaStatus::Processed !== $media->status()) {
                    continue;
                }
                if (null !== $this->queue($resource, $media, $actor)) {
                    ++$launched;
                }
            }
        }
        $this->entityManager->flush();

        return $launched;
    }

    /**
     * Déclenchement automatique à l'import : appelé par MediaUploadedHandler
     * après la génération des renditions, dans la transaction du worker — le
     * flush appartient à l'appelant.
     */
    public function scheduleForMedia(MediaAsset $media, string $createdBy): void
    {
        $resource = $this->resources->findOneByMediaId($media->id());
        if (null === $resource || null === $resource->fiche()) {
            return;
        }
        $this->queue($resource, $media, $createdBy);
    }

    private function queue(RessourceLieu $resource, MediaAsset $media, string $createdBy): ?ImageRecognition
    {
        $fiche = $resource->fiche();
        if (null === $fiche || $this->recognitions->hasActiveForResource($resource)) {
            return null;
        }
        $recognition = new ImageRecognition(
            $fiche,
            $media,
            $resource,
            $this->parametres->string('openai.reco_prompt'),
            $this->parametres->string('openai.reco_modele'),
            $createdBy,
        );
        $this->entityManager->persist($recognition);
        $this->outbox->enqueue(new RecognizeImage($recognition->id()));

        return $recognition;
    }

    public function retry(string $id): void
    {
        $recognition = $this->recognitions->find($id);
        if (!$recognition instanceof ImageRecognition) {
            throw new \DomainException('Reconnaissance introuvable.');
        }
        $recognition->requeue();
        $this->outbox->enqueue(new RecognizeImage($recognition->id()));
        $this->entityManager->flush();
    }
}
