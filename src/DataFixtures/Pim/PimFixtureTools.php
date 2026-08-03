<?php

declare(strict_types=1);

namespace App\DataFixtures\Pim;

use Doctrine\ORM\EntityManagerInterface;

final class PimFixtureTools
{
    private const MAX_COUNT = 100_000;

    public static function count(string $environmentVariable, int $default): int
    {
        $configured = getenv($environmentVariable);

        if (false === $configured || '' === trim($configured)) {
            return $default;
        }

        if (1 !== preg_match('/^\d+$/', $configured)) {
            throw new \InvalidArgumentException(sprintf(
                '%s doit être un entier positif.',
                $environmentVariable,
            ));
        }

        $count = (int) $configured;

        if ($count < 1 || $count > self::MAX_COUNT) {
            throw new \InvalidArgumentException(sprintf(
                '%s doit être compris entre 1 et %d.',
                $environmentVariable,
                self::MAX_COUNT,
            ));
        }

        return $count;
    }

    public static function nextCode(EntityManagerInterface $manager): int
    {
        $lastValue = $manager->getConnection()->fetchOne(
            'SELECT last_value FROM pim_fiche_code_counter WHERE id = 1',
        );

        return (false === $lastValue ? 0 : (int) $lastValue) + 1;
    }
}
