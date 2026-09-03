<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Message\InternalUserInvited;
use App\Account\Message\InternalUserPasswordResetRequested;
use App\Dam\Message\AnalyzeMedia;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\PublishDocument;
use App\Dam\Message\RegenerateMedia;
use App\Dam\Message\UnpublishDocument;
use App\Pim\Message\IndexFiche;
use App\Pim\Service\AdminEventCatalog;
use App\Shared\Message\MediaProcessed;
use App\Shared\Message\MediaUploaded;
use PHPUnit\Framework\TestCase;

final class AdminEventCatalogTest extends TestCase
{
    public function testItDocumentsEveryBusinessEventAndItsHandler(): void
    {
        $events = (new AdminEventCatalog())->events();
        self::assertEqualsCanonicalizing(
            [
                InternalUserInvited::class,
                InternalUserPasswordResetRequested::class,
                AnalyzeMedia::class,
                DeleteMedia::class,
                PublishDocument::class,
                RegenerateMedia::class,
                UnpublishDocument::class,
                IndexFiche::class,
                MediaProcessed::class,
                MediaUploaded::class,
            ],
            array_column($events, 'type'),
        );
        foreach ($events as $event) {
            self::assertNotSame('', $event['trigger']);
            self::assertNotSame('', $event['result']);
            self::assertTrue(class_exists($event['handler']));
            self::assertContains($event['transport'], ['pim', 'dam', 'mail']);
        }
        $eventsByType = array_column($events, null, 'type');
        self::assertStringContainsString(
            'Lieu, Activité, Restaurant ou Service',
            $eventsByType[MediaUploaded::class]['trigger'],
        );
        self::assertStringContainsString(
            'Lieu, Activité, Restaurant ou Service',
            $eventsByType[IndexFiche::class]['trigger'],
        );
        self::assertSame(
            'worker-dam',
            $eventsByType[PublishDocument::class]['worker'],
        );
        self::assertSame(
            'worker-dam',
            $eventsByType[UnpublishDocument::class]['worker'],
        );
    }
}
