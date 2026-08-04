<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class RepositoryBoundaryTest extends TestCase
{
    public function testBusinessQueriesStayInRepositories(): void
    {
        $sourceRoot = dirname(__DIR__, 2).'/src';
        $violations = [];
        foreach (['Account', 'Dam', 'Enrichment', 'Pim'] as $domain) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot.'/'.$domain));
            foreach ($iterator as $file) {
                $path = $file->getPathname();
                if (!$file->isFile() || 'php' !== $file->getExtension() || str_contains($path, '/Repository/')) {
                    continue;
                }
                $source = (string) file_get_contents($path);
                foreach ([
                    'Doctrine\\DBAL\\Connection',
                    '->getRepository(',
                    '->createQueryBuilder(',
                    '->executeQuery(',
                    '->executeStatement(',
                    '->fetchAssociative(',
                    '->fetchAllAssociative(',
                    '->fetchOne(',
                    '$this->entityManager->find(',
                ] as $forbidden) {
                    if (str_contains($source, $forbidden)) {
                        $violations[] = sprintf('%s contient %s', substr($path, strlen($sourceRoot) + 1), $forbidden);
                    }
                }
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }
}
