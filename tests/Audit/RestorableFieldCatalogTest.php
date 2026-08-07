<?php

declare(strict_types=1);

namespace App\Tests\Audit;

use App\Audit\Restore\RestorableFieldCatalog;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RestorableFieldCatalogTest extends TestCase
{
    private RestorableFieldCatalog $catalog;

    protected function setUp(): void
    {
        $this->catalog = new RestorableFieldCatalog(
            $this->createStub(EntityManagerInterface::class),
        );
    }

    public function testScalarPathsWithConventionalSettersAreRestorable(): void
    {
        self::assertTrue($this->catalog->isRestorable('nom'));
        self::assertTrue($this->catalog->isRestorable('lieu.label'));
        self::assertTrue($this->catalog->isRestorable('localisation.ville'));
        self::assertTrue(
            $this->catalog->isRestorable('service.tarifParHeure'),
        );
    }

    public function testWorkflowCollectionsAndUnknownFieldsAreExcluded(): void
    {
        self::assertFalse($this->catalog->isRestorable('workflow.status'));
        self::assertFalse(
            $this->catalog->isRestorable(
                'salles[01JABCDEFGHJKMNPQRSTVWXYZ0].nom',
            ),
        );
        self::assertFalse(
            $this->catalog->isRestorable('attributs[TYPE_CUISINE].valueId'),
        );
        self::assertFalse(
            $this->catalog->isRestorable(
                'medias[01JABCDEFGHJKMNPQRSTVWXYZ0].caption',
            ),
        );
        self::assertFalse($this->catalog->isRestorable('fiche.updatedAt'));
        self::assertFalse($this->catalog->isRestorable('fiche.champDisparu'));
        self::assertFalse($this->catalog->isRestorable('inconnu.champ'));
        self::assertFalse($this->catalog->isRestorable('fiche'));
    }
}
