<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ControllerArchitectureTest extends TestCase
{
    public function testControllersOnlyContainPublicRouteActionsAndHttpOrchestration(): void
    {
        $violations = [];
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/src') as $file) {
            if (!str_contains($file, '/Controller/') || !str_ends_with($file, 'Controller.php')) {
                continue;
            }
            $source = (string) file_get_contents($file);
            foreach ([
                'Doctrine\\ORM\\EntityManagerInterface',
                'Doctrine\\DBAL\\Connection',
                '->getRepository(',
                '->createQueryBuilder(',
                '->persist(',
                '->flush(',
                'function __construct(',
                'private function ',
                'protected function ',
            ] as $forbidden) {
                if (str_contains($source, $forbidden)) {
                    $violations[] = sprintf('%s contient %s', $this->relative($file), $forbidden);
                }
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }

    /** @return list<string> */
    private function phpFilesIn(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file->isFile() && 'php' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function relative(string $file): string
    {
        return substr($file, strlen(dirname(__DIR__, 2)) + 1);
    }
}
