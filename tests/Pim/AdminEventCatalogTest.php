<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Account\Message\InternalUserInvited;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\RegenerateMedia;
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

        self::assertEqualsCanonicalizing([
            InternalUserInvited::class,
            DeleteMedia::class,
            RegenerateMedia::class,
            IndexFiche::class,
            MediaProcessed::class,
            MediaUploaded::class,
        ], array_column($events, 'type'));

        foreach ($events as $event) {
            self::assertNotSame('', $event['trigger']);
            self::assertNotSame('', $event['result']);
            self::assertTrue(class_exists($event['handler']));
            self::assertContains($event['transport'], ['pim', 'dam', 'mail']);
        }
    }
}
