<?php

declare(strict_types=1);

namespace App\Vision\MessageHandler;

use App\Dam\Enum\MediaStatus;
use App\Dam\Service\PublicMediaUrlGenerator;
use App\Vision\Entity\ImageRecognition;
use App\Vision\Entity\ImageRecognitionSuggestion;
use App\Vision\Enum\RecognitionStatus;
use App\Vision\Message\RecognizeImage;
use App\Vision\Repository\ImageRecognitionRepository;
use App\Vision\Service\ImageRecognitionProviderInterface;
use App\Vision\Service\OpenAiProviderException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class RecognizeImageHandler
{
    public function __construct(
        private ImageRecognitionRepository $recognitions,
        private ImageRecognitionProviderInterface $provider,
        private PublicMediaUrlGenerator $urlGenerator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(RecognizeImage $message): void
    {
        $recognition = $this->recognitions->find($message->recognitionId);
        if (!$recognition instanceof ImageRecognition || !in_array($recognition->status(), [RecognitionStatus::Queued, RecognitionStatus::Failed], true)) {
            return;
        }
        $media = $recognition->media();
        if (in_array($media->status(), [MediaStatus::Deleting, MediaStatus::Deleted], true)) {
            $recognition->fail('Le média a été supprimé depuis le lancement de la reconnaissance.');

            return;
        }
        if ($media->checksum() !== $recognition->sourceChecksum()) {
            $recognition->fail('Le média a été remplacé depuis le lancement de la reconnaissance.');

            return;
        }
        // Le fournisseur lit la photo par son URL publique : pas de transfert
        // de l'original, la rendition large (CDN) suffit à l'analyse.
        $rendition = $media->rendition('large');
        if (null === $rendition) {
            $recognition->fail('La rendition publique « large » du média est introuvable.');

            return;
        }
        try {
            $recognition->start();
            $result = $this->provider->describe(
                $this->urlGenerator->url($rendition->storageKey()),
                $recognition->prompt(),
                $recognition->providerModel(),
            );
            $resource = $recognition->resource();
            $this->entityManager->persist(new ImageRecognitionSuggestion($recognition, ImageRecognitionSuggestion::PATH_LEGENDE, 'Légende', $result->legende, $resource->legende()));
            if ([] !== $result->keywords) {
                $this->entityManager->persist(new ImageRecognitionSuggestion($recognition, ImageRecognitionSuggestion::PATH_KEYWORDS, 'Mots-clés', $result->keywords, $resource->keywords()));
            }
            $labels = ['vue_type' => 'Type de vue', 'interieur_exterieur' => 'Intérieur / extérieur'];
            foreach ($result->extras as $key => $value) {
                $this->entityManager->persist(new ImageRecognitionSuggestion($recognition, 'extras.'.$key, $labels[$key] ?? $key, $value, null));
            }
            $recognition->complete($result->raw);
        } catch (OpenAiProviderException $error) {
            if ($error->retryable) {
                throw new RecoverableMessageHandlingException($error->getMessage(), previous: $error, retryDelay: 1000 * ($error->retryAfter ?? 10));
            }
            $recognition->fail($error->getMessage());
        } catch (\DomainException $error) {
            $recognition->fail($error->getMessage());
        }
    }
}
