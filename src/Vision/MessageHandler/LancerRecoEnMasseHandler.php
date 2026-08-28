<?php

declare(strict_types=1);

namespace App\Vision\MessageHandler;

use App\Shared\Outbox\OutboxPublisherInterface;
use App\Vision\Message\LancerRecoEnMasse;
use App\Vision\Repository\ImageRecognitionRepository;
use App\Vision\Service\ImageRecognitionManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Une vague de lancement en masse, puis auto-repostage tant qu'il reste des
 * photos sans mots-clés. La chaîne s'arrête d'elle-même quand une vague ne
 * lance plus rien (il ne reste que des photos non lançables) ou si l'IA est
 * désactivée entre deux vagues.
 */
#[AsMessageHandler]
final readonly class LancerRecoEnMasseHandler
{
    public function __construct(
        private ImageRecognitionManager $manager,
        private ImageRecognitionRepository $recognitions,
        private OutboxPublisherInterface $outbox,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(LancerRecoEnMasse $message): void
    {
        try {
            $launched = $this->manager->launchForPhotosSansMotsCles($message->actor, ImageRecognitionManager::VAGUE_MASSE);
        } catch (\DomainException $error) {
            $this->logger->warning('Lancement en masse de la reconnaissance IA interrompu.', ['raison' => $error->getMessage()]);

            return;
        }
        if ($launched > 0 && $this->recognitions->countPhotosSansMotsClesSansAnalyse() > 0) {
            $this->outbox->enqueue(new LancerRecoEnMasse($message->actor));
            $this->entityManager->flush();
        }
    }
}
