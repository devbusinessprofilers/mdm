<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Enum\TypeParametre;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Ulid;

/**
 * Surcharge un paramètre applicatif dans la base de test, que sa ligne
 * existe déjà (créée par les migrations) ou non : un test ne doit pas dépendre
 * de l'état laissé par les migrations ou par un autre test.
 */
final class ParametreEnBase
{
    public static function fixer(Connection $connection, string $nom, ?string $valeur, TypeParametre $type = TypeParametre::Booleen): void
    {
        $connection->executeStatement(
            'INSERT INTO parametre (id, nom, description, type, valeur, updated_at) VALUES (?, ?, ?, ?, ?, NOW())'
            .' ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), updated_at = NOW()',
            [(new Ulid())->toBinary(), $nom, 'Paramètre de test.', $type->value, $valeur],
        );
    }

    private function __construct()
    {
    }
}
