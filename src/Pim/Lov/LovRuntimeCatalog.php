<?php

declare(strict_types=1);

namespace App\Pim\Lov;

use Doctrine\DBAL\Exception;
use App\Pim\Repository\ValeurAttributRepository;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

final class LovRuntimeCatalog
{
    /** @var array<string, array<string, array{id: int, label: string, active: bool}>> */
    private static array $values = [];
    /** @var array<int, array{attribute: string, code: string}> */
    private static array $ids = [];

    public function __construct(private readonly ValeurAttributRepository $valuesRepository) {}

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

    // Les commandes console lisent aussi les LOV des fiches : sans ce
    // rechargement, une valeur créée à chaud (accept d'une chaîne hors liste,
    // admin des listes de valeurs) reste inconnue du catalogue et fait échouer
    // les scans (« Unknown LOV value id »).
    #[AsEventListener]
    public function onConsole(ConsoleCommandEvent $event): void
    {
        unset($event);
        $this->reload();
    }

    public function reload(): void
    {
        try {
            $rows = $this->valuesRepository->findRuntimeRows();
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
