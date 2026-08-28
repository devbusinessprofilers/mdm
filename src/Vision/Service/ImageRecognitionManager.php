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
use App\Vision\Message\LancerRecoEnMasse;
use App\Vision\Message\RecognizeImage;
use App\Vision\Repository\ImageRecognitionRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ImageRecognitionManager
{
    /** Taille d'une vague du lancement en masse : borne chaque passage du worker. */
    public const VAGUE_MASSE = 500;

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

    /** Place la chaîne du lancement en masse en tâche de fond (une vague par message). */
    public function scheduleMassLaunch(string $actor): void
    {
        if (!$this->parametres->bool('openai.actif')) {
            throw new \DomainException('La reconnaissance IA est désactivée (OPENAI_ENABLED).');
        }
        $this->outbox->enqueue(new LancerRecoEnMasse($actor));
        $this->entityManager->flush();
    }

    /**
     * Une vague du lancement en masse : les photos dont le champ mots-clés est
     * vide, bornée pour garder chaque passage court. L'enchaînement des vagues
     * est porté par LancerRecoEnMasseHandler.
     *
     * Balayage par fenêtres : les photos non lançables (média manquant, non
     * image ou pas encore traité) restent dans l'assiette de la requête,
     * l'offset les enjambe pour que la vague atteigne les photos lançables
     * suivantes.
     *
     * @return int nombre de reconnaissances lancées
     */
    public function launchForPhotosSansMotsCles(string $actor, int $limit): int
    {
        if (!$this->parametres->bool('openai.actif')) {
            throw new \DomainException('La reconnaissance IA est désactivée (OPENAI_ENABLED).');
        }
        $launched = 0;
        $offset = 0;
        while ($launched < $limit) {
            $batch = $this->recognitions->findPhotosSansMotsClesSansAnalyse($limit, $offset);
            if ([] === $batch) {
                break;
            }
            foreach ($batch as $resource) {
                $media = '' === $resource->damAssetId() ? null : $this->mediaRepository->find($resource->damAssetId());
                if (null === $media || MediaKind::Image !== $media->kind() || MediaStatus::Processed !== $media->status()) {
                    continue;
                }
                if (null !== $this->queue($resource, $media, $actor) && ++$launched >= $limit) {
                    break;
                }
            }
            $offset += count($batch);
        }
        $this->entityManager->flush();

        return $launched;
    }

    /**
     * Lancement depuis la modale de paramètres d'une photo (bouton
     * « Enrichir l'image ») : une seule ressource.
     *
     * @return bool false quand une reconnaissance est déjà en cours pour la photo
     */
    public function launchForResource(RessourceLieu $resource, string $actor): bool
    {
        if (!$this->parametres->bool('openai.actif')) {
            throw new \DomainException('La reconnaissance IA est désactivée (OPENAI_ENABLED).');
        }
        $media = '' === $resource->damAssetId() ? null : $this->mediaRepository->find($resource->damAssetId());
        if (null === $media || MediaKind::Image !== $media->kind() || MediaStatus::Processed !== $media->status()) {
            throw new \DomainException('Cette photo n’est pas encore traitée : relancez le traitement avant l’enrichissement.');
        }
        $launched = null !== $this->queue($resource, $media, $actor);
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
