<?php

declare(strict_types=1);

namespace App\Shared\Alert;

use App\Shared\Service\ParametreProviderInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Envoie les alertes applicatives par email, avec déduplication : une même
 * empreinte (type + fingerprint) n'est notifiée qu'une fois par heure. La
 * déduplication s'appuie sur le cache local du conteneur — partagée seulement
 * si le cache app est externalisé (Redis).
 */
final readonly class AlertNotifier
{
    private const DEDUP_TTL_SECONDS = 3600;

    public function __construct(
        private MailerInterface $mailer,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        private ParametreProviderInterface $parametres,
        #[Autowire(env: 'MAILER_FROM')]
        private string $sender,
    ) {
    }

    public function notify(string $type, string $fingerprint, string $subject, string $body): void
    {
        $recipient = $this->parametres->string('alerte.email');
        if ('' === $recipient) {
            return;
        }
        $item = $this->cache->getItem('alert_dedup_'.$type.'_'.hash('sha256', $fingerprint));
        if ($item->isHit()) {
            return;
        }
        $item->set(true);
        $item->expiresAfter(self::DEDUP_TTL_SECONDS);
        $this->cache->save($item);
        try {
            $this->mailer->send(
                (new Email())
                    ->from($this->sender)
                    ->to($recipient)
                    ->subject('[MDM][ALERTE] '.$subject)
                    ->text($body),
            );
        } catch (\Throwable $exception) {
            // Une alerte ne doit jamais faire échouer le traitement qui l'a déclenchée.
            $this->logger->error('alert.send_failed', ['type' => $type, 'exception' => $exception->getMessage()]);
        }
    }
}
