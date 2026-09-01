<?php

declare(strict_types=1);

namespace App\Vision\Service;

use App\Dam\Enum\MediaKind;
use App\Dam\Enum\MediaStatus;
use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\NatureRessource;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Service\ParametreProviderInterface;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Vision\Entity\ImageEnhancement;
use App\Vision\Enum\EnhancementProvider;
use App\Vision\Message\ApplyImageEnhancement;
use App\Vision\Message\EnhanceImage;
use App\Vision\Repository\ImageEnhancementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('vision')]
final readonly class ImageEnhancementManager
{
    public function __construct(
        private ImageEnhancementRepository $enhancements,
        private RessourceLieuRepository $resources,
        private MediaAssetRepository $mediaRepository,
        private ParametreProviderInterface $parametres,
        private OutboxPublisherInterface $outbox,
        private PrivateObjectStorageInterface $privateStorage,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Met en file une retouche pour chaque photo exploitable des fiches
     * sélectionnées. Prompt et modèle sont figés au lancement : un changement
     * de paramètre en cours de route ne réécrit pas l'historique.
     *
     * @param list<Fiche> $fiches
     *
     * @return int nombre de retouches lancées
     */
    public function launchForFiches(array $fiches, string $actor, EnhancementProvider $provider = EnhancementProvider::OpenAi): int
    {
        if (EnhancementProvider::OpenAi === $provider) {
            if (!$this->parametres->bool('openai.actif')) {
                throw new \DomainException('La retouche IA est désactivée (OPENAI_ENABLED).');
            }
            $prompt = $this->parametres->string('openai.retouche_prompt');
            $model = $this->parametres->string('openai.retouche_modele');
        } else {
            // Retouche locale : gratuite et sans service tiers, donc sans gate.
            $prompt = ImageMagickEnhancementProvider::DESCRIPTION;
            $model = ImageMagickEnhancementProvider::MODEL;
        }
        $launched = 0;
        foreach ($fiches as $fiche) {
            $photos = $this->resources->findBy(['fiche' => $fiche, 'nature' => NatureRessource::Photo]);
            foreach ($photos as $resource) {
                $media = '' === $resource->damAssetId() ? null : $this->mediaRepository->find($resource->damAssetId());
                if (null === $media || MediaKind::Image !== $media->kind() || MediaStatus::Processed !== $media->status()) {
                    continue;
                }
                if ($this->enhancements->hasActiveForMedia($media->id())) {
                    continue;
                }
                $enhancement = new ImageEnhancement($fiche, $media, $resource, $prompt, $model, $actor, $provider);
                $this->entityManager->persist($enhancement);
                $this->outbox->enqueue(new EnhanceImage($enhancement->id()));
                ++$launched;
            }
        }
        $this->entityManager->flush();

        return $launched;
    }

    public function accept(string $id, string $actor): void
    {
        $enhancement = $this->get($id);
        $enhancement->accept($actor);
        $resultKey = $enhancement->resultStorageKey();
        $resultChecksum = $enhancement->resultChecksum();
        if (null === $resultKey || null === $resultChecksum) {
            throw new \DomainException('La retouche prête n’a pas de résultat exploitable.');
        }
        $enhancement->media()->applyEnhancedSource($resultKey, $resultChecksum);
        // La sortie du fournisseur a ses propres dimensions : un recadrage
        // posé sur l'original deviendrait des coordonnées hors sujet.
        $resource = $enhancement->resource() ?? $this->resources->findOneByMediaId($enhancement->media()->id());
        if (null !== $resource) {
            $resource->changeCrop(null, null, null, null);
            $resource->changeRotation(0);
        }
        $this->outbox->enqueue(new ApplyImageEnhancement($enhancement->media()->id()));
        $this->entityManager->flush();
    }

    public function reject(string $id, string $actor): void
    {
        $enhancement = $this->get($id);
        $enhancement->reject($actor);
        $key = $enhancement->resultStorageKey();
        $this->entityManager->flush();
        // Purge après le flush : un échec de suppression ne laisse qu'un
        // orphelin S3 bénin et ne doit pas annuler la décision.
        if (null !== $key) {
            try {
                $this->privateStorage->delete($key);
            } catch (\Throwable $error) {
                $this->logger->warning('La purge de la candidate de retouche rejetée a échoué.', [
                    'enhancement_id' => $enhancement->id(),
                    'storage_key' => $key,
                    'exception' => $error,
                ]);
            }
        }
    }

    public function retry(string $id): void
    {
        $enhancement = $this->get($id);
        $enhancement->requeue();
        $this->outbox->enqueue(new EnhanceImage($enhancement->id()));
        $this->entityManager->flush();
    }

    /** Retour arrière : l'original redevient la source des renditions. */
    public function revert(string $mediaId): void
    {
        $media = $this->mediaRepository->find($mediaId);
        if (null === $media || !$media->isEnhanced()) {
            throw new \DomainException('Ce média n’a pas de retouche appliquée.');
        }
        $media->revertToOriginal();
        $this->outbox->enqueue(new ApplyImageEnhancement($media->id()));
        $this->entityManager->flush();
    }

    private function get(string $id): ImageEnhancement
    {
        $enhancement = $this->enhancements->find($id);
        if (!$enhancement instanceof ImageEnhancement) {
            throw new \DomainException('Retouche introuvable.');
        }

        return $enhancement;
    }
}
