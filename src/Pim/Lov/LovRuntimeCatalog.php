<?php

declare(strict_types=1);

namespace App\Pim\Lov;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

final class LovRuntimeCatalog
{
    /** @var array<string, array<string, array{id: int, label: string, active: bool}>> */
    private static array $values = [];
    /** @var array<int, array{attribute: string, code: string}> */
    private static array $ids = [];

    public function __construct(private readonly Connection $connection) {}

    #[AsEventListener(priority: 100)]
    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) { $this->reload(); }
    }

    #[AsEventListener]
    public function onMessage(WorkerMessageReceivedEvent $event): void
    {
        unset($event);
        $this->reload();
    }

    public function reload(): void
    {
        try {
            $schema = $this->connection->createSchemaManager();
            if (!$schema->tablesExist(['pim_attribute_value', 'pim_attribute_definition'])) { return; }
            $hasActive = isset($schema->listTableColumns('pim_attribute_value')['active']);
            $rows = $this->connection->fetchAllAssociative(sprintf(
                "SELECT a.code attribute_code, v.id, v.code, v.label, %s active FROM pim_attribute_value v INNER JOIN pim_attribute_definition a ON a.id = v.attribute_id WHERE a.code <> 'PRESTATAIRE' ORDER BY a.code, v.position, v.id",
                $hasActive ? 'v.active' : '1',
            ));
        } catch (Exception) {
            // Keep the static catalogs usable during installation and in tests without a database.
            return;
        }
        self::$values = self::$ids = [];
        foreach ($rows as $row) {
            $attribute = (string) $row['attribute_code'];
            $code = (string) $row['code'];
            $id = (int) $row['id'];
            self::$values[$attribute][$code] = ['id' => $id, 'label' => (string) $row['label'], 'active' => (bool) $row['active']];
            self::$ids[$id] = ['attribute' => $attribute, 'code' => $code];
        }
    }

    /** @return array<string, string>|null */
    public static function choices(string $attribute): ?array
    {
        if (!isset(self::$values[$attribute])) { return null; }
        $result = [];
        foreach (self::$values[$attribute] as $code => $value) {
            if ($value['active']) { $result[$code] = $value['label']; }
        }

        return $result;
    }

    public static function valueId(string $attribute, string $code): ?int { return self::$values[$attribute][$code]['id'] ?? null; }
    public static function valueCode(int $id): ?string { return self::$ids[$id]['code'] ?? null; }
}
