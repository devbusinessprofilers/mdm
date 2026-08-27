<?php

declare(strict_types=1);

namespace App\Shared\Monolog;

use Doctrine\DBAL\Connection;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Utils;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Persiste les logs dans la table log_entry pour la visionneuse de
 * /admin/performance : en prod tout part en stderr, la BDD est le seul canal
 * que l'application peut relire, dans tous les environnements et depuis tous
 * les processus (workers compris).
 *
 * Volume maîtrisé : warning et plus pour tout le monde, info et plus pour les
 * channels métier (déroulé normal des jobs d'intégration). Écrit sur une
 * connexion DBAL dédiée (doctrine.yaml, connexion « logs ») : hors des
 * transactions applicatives et insensible à l'état de la connexion par
 * défaut. Gardes anti-boucle en couches : channel doctrine exclu en config,
 * drapeau de ré-entrance, échec d'écriture avalé (l'erreur d'origine part de
 * toute façon sur stderr ou dans le fichier).
 */
final class DoctrineDbalLogHandler extends AbstractProcessingHandler
{
    /** Channels métier persistés dès info (les autres à partir de warning). */
    public const INFO_CHANNELS = ['enrichment', 'marketplace_sync', 'salesforce', 'vision', 'mail', 'audit'];

    private const MAX_JSON_BYTES = 65000;
    private const MAX_MESSAGE_BYTES = 65000;

    private bool $writing = false;
    private readonly NormalizerFormatter $normalizer;
    private readonly string $hostname;

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.logs_connection')]
        private readonly Connection $connection,
    ) {
        parent::__construct(Level::Info);
        $this->normalizer = new NormalizerFormatter();
        $this->hostname = substr(gethostname() ?: '', 0, 64);
    }

    protected function write(LogRecord $record): void
    {
        if ($record->level->value < Level::Warning->value && !in_array($record->channel, self::INFO_CHANNELS, true)) {
            return;
        }
        if ($this->writing) {
            return; // log émis pendant l'INSERT lui-même : on coupe la boucle
        }
        $this->writing = true;
        try {
            /** @var array{message?: string, context?: array<mixed>, extra?: array<mixed>} $normalized */
            $normalized = $this->normalizer->format($record);
            $requestId = $record->extra['request_id'] ?? null;
            $this->connection->insert('log_entry', [
                'logged_at' => $record->datetime->format('Y-m-d H:i:s'),
                'channel' => mb_substr($record->channel, 0, 64),
                'level' => $record->level->value,
                'message' => mb_substr($record->message, 0, self::MAX_MESSAGE_BYTES),
                'context' => $this->toJson($normalized['context'] ?? []),
                'extra' => $this->toJson($normalized['extra'] ?? []),
                'request_id' => is_string($requestId) ? mb_substr($requestId, 0, 36) : null,
                'hostname' => '' !== $this->hostname ? $this->hostname : null,
            ]);
        } catch (\Throwable) {
            // Silencieux par conception : une panne BDD ne doit ni casser la
            // requête ou le worker en cours, ni déclencher un nouveau log.
        } finally {
            $this->writing = false;
        }
    }

    /**
     * @param array<mixed> $data
     */
    private function toJson(array $data): ?string
    {
        if ([] === $data) {
            return null;
        }
        $json = Utils::jsonEncode($data, ignoreErrors: true);

        return \strlen($json) > self::MAX_JSON_BYTES
            ? Utils::jsonEncode(['_truncated' => true], ignoreErrors: true)
            : $json;
    }
}
