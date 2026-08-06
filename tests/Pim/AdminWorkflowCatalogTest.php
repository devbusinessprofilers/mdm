<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\AdminWorkflowCatalog;
use PHPUnit\Framework\TestCase;

final class AdminWorkflowCatalogTest extends TestCase
{
    public function testItDocumentsPimApiDamAndOperationsWorkflows(): void
    {
        $workflows = (new AdminWorkflowCatalog())->workflows();
        self::assertSame(
            ['PIM', 'API', 'DAM', 'Audit', 'Ops'],
            array_column($workflows, 'domain'),
        );
        self::assertStringContainsString(
            'If-Match',
            implode(' ', $workflows[1]['steps']),
        );
        self::assertStringContainsString(
            'Restaurant',
            implode(' ', $workflows[0]['steps']),
        );
        self::assertStringContainsString(
            'modification par un validateur conserve le statut',
            implode(' ', $workflows[0]['rules']),
        );
        self::assertStringContainsString(
            'documents:private',
            implode(' ', $workflows[2]['rules']),
        );
        self::assertStringContainsString(
            'seul un validateur interne',
            implode(' ', $workflows[1]['rules']),
        );
        self::assertStringContainsString(
            'pHash',
            implode(' ', $workflows[2]['steps']),
        );
        self::assertStringContainsString(
            'append-only',
            $workflows[3]['summary'],
        );
        self::assertStringContainsString(
            'doctrine:migrations:migrate',
            implode(' ', $workflows[4]['steps']),
        );
        foreach ($workflows as $workflow) {
            self::assertNotSame('', $workflow['summary']);
            self::assertNotEmpty($workflow['steps']);
            self::assertNotEmpty($workflow['rules']);
        }
    }
}
