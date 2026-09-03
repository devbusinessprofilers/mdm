<?php

declare(strict_types=1);

namespace App\Vision\MessageHandler;

use App\Dam\Enum\MediaStatus;
use App\Shared\Service\CopieLocale;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Vision\Entity\ImageEnhancement;
use App\Vision\Enum\EnhancementProvider;
use App\Vision\Enum\EnhancementStatus;
use App\Vision\Message\EnhanceImage;
use App\Vision\Repository\ImageEnhancementRepository;
use App\Vision\Service\ImageEnhancementProviderInterface;
use App\Vision\Service\ImageMagickEnhancementProvider;
use App\Vision\Service\OpenAiProviderException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EnhanceImageHandler
{
    public function __construct(
        private ImageEnhancementRepository $enhancements,
        private ImageEnhancementProviderInterface $provider,
        private ImageMagickEnhancementProvider $imageMagick,
        private PrivateObjectStorageInterface $storage,
    ) {
    }

    public function __invoke(EnhanceImage $message): void
    {
        $enhancement = $this->enhancements->find($message->enhancementId);
        if (!$enhancement instanceof ImageEnhancement || !in_array($enhancement->status(), [EnhancementStatus::Queued, EnhancementStatus::Failed], true)) {
            return;
        }
        $media = $enhancement->media();
        if (in_array($media->status(), [MediaStatus::Deleting, MediaStatus::Deleted], true)) {
            $enhancement->fail('Le média a été supprimé depuis le lancement de la retouche.');

            return;
        }
        // La retouche part toujours de l'original déposé, jamais d'une
        // retouche précédente : pas de dérive par passes successives.
        $temp = CopieLocale::depuis($this->storage, $media->originalStorageKey(), 'mdm-vision-source-');
        try {
            if (hash_file('sha256', $temp) !== $enhancement->sourceChecksum()) {
                throw new \DomainException('L’empreinte de l’original ne correspond plus au lancement de la retouche.');
            }
            $enhancement->start();
            $provider = EnhancementProvider::ImageMagick === $enhancement->provider() ? $this->imageMagick : $this->provider;
            $result = $provider->enhance($temp, $media->mimeType(), $enhancement->prompt(), $enhancement->providerModel());
            $extension = match ($result->mimeType) {
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                default => 'png',
            };
            $key = \dirname($media->originalStorageKey()).'/retouche/'.$enhancement->id().'.'.$extension;
            $this->storage->write($key, $result->bytes, ['ContentType' => $result->mimeType]);
            $enhancement->complete($key, hash('sha256', $result->bytes), strlen($result->bytes), $result->raw);
        } catch (OpenAiProviderException $error) {
            if ($error->retryable) {
                throw $error->relance(10);
            }
            $enhancement->fail($error->getMessage());
        } catch (\DomainException $error) {
            $enhancement->fail($error->getMessage());
        } finally {
            CopieLocale::supprimer($temp);
        }
    }
}
