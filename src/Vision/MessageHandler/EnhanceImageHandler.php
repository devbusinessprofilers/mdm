<?php

declare(strict_types=1);

namespace App\Vision\MessageHandler;

use App\Dam\Enum\MediaStatus;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Vision\Entity\ImageEnhancement;
use App\Vision\Enum\EnhancementStatus;
use App\Vision\Message\EnhanceImage;
use App\Vision\Repository\ImageEnhancementRepository;
use App\Vision\Service\ImageEnhancementProviderInterface;
use App\Vision\Service\OpenAiProviderException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class EnhanceImageHandler
{
    public function __construct(
        private ImageEnhancementRepository $enhancements,
        private ImageEnhancementProviderInterface $provider,
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
        $temp = tempnam(sys_get_temp_dir(), 'mdm-vision-source-');
        if (false === $temp) {
            throw new RecoverableMessageHandlingException('Impossible de créer la copie locale de l’original.');
        }
        try {
            // La retouche part toujours de l'original déposé, jamais d'une
            // retouche précédente : pas de dérive par passes successives.
            $source = $this->storage->readStream($media->originalStorageKey());
            try {
                $destination = fopen($temp, 'wb');
                if (false === $destination) {
                    throw new \RuntimeException('Impossible d’écrire la copie locale de l’original.');
                }
                try {
                    stream_copy_to_stream($source, $destination);
                } finally {
                    fclose($destination);
                }
            } finally {
                fclose($source);
            }
            if (hash_file('sha256', $temp) !== $enhancement->sourceChecksum()) {
                throw new \DomainException('L’empreinte de l’original ne correspond plus au lancement de la retouche.');
            }
            $enhancement->start();
            $result = $this->provider->enhance($temp, $media->mimeType(), $enhancement->prompt(), $enhancement->providerModel());
            $key = \dirname($media->originalStorageKey()).'/retouche/'.$enhancement->id().'.png';
            $this->storage->write($key, $result->bytes, ['ContentType' => $result->mimeType]);
            $enhancement->complete($key, hash('sha256', $result->bytes), strlen($result->bytes), $result->raw);
        } catch (OpenAiProviderException $error) {
            if ($error->retryable) {
                throw new RecoverableMessageHandlingException($error->getMessage(), previous: $error, retryDelay: 1000 * ($error->retryAfter ?? 10));
            }
            $enhancement->fail($error->getMessage());
        } catch (\DomainException $error) {
            $enhancement->fail($error->getMessage());
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }
}
