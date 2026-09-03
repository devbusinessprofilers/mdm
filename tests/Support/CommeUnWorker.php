<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Exécute un handler Messenger comme le ferait un worker : l'appel, puis le
 * flush que ReceivedDoctrineTransactionMiddleware fait à la fin du handler.
 * Les handlers ne flushent pas eux-mêmes (voir src/Shared/README.md).
 */
final class CommeUnWorker
{
    public static function traiter(EntityManagerInterface $entityManager, callable $handler, object $message): void
    {
        $handler($message);
        $entityManager->flush();
    }

    private function __construct()
    {
    }
}
