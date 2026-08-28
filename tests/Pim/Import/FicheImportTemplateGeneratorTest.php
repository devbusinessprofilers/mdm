<?php

declare(strict_types=1);

namespace App\Tests\Pim\Import;

use App\Pim\Enum\TypeFiche;
use App\Pim\Import\FicheImportTemplateGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FicheImportTemplateGeneratorTest extends KernelTestCase
{
    public function testLieuHeadersCoverColumnsAndCollections(): void
    {
        self::bootKernel();
        $generator = self::getContainer()->get(FicheImportTemplateGenerator::class);

        $headers = $generator->headers(TypeFiche::Lieu);
        self::assertContains('salle_1_nom', $headers);
        self::assertContains('attribution_visibilite', $headers);
        self::assertContains('salle_20_climatisee', $headers);
        self::assertContains('periode_fermeture_10_date_fin', $headers);
        self::assertNotContains('salle_21_nom', $headers);
    }

    public function testHeadersAreUniqueForEveryType(): void
    {
        self::bootKernel();
        $generator = self::getContainer()->get(FicheImportTemplateGenerator::class);

        foreach ([TypeFiche::Lieu, TypeFiche::Activite, TypeFiche::Restaurant, TypeFiche::ServiceEvenementiel] as $type) {
            $headers = $generator->headers($type);
            self::assertSame(count($headers), count(array_unique($headers)), sprintf('En-têtes dupliqués pour %s.', $type->value));
            self::assertContains('code', $headers);
            self::assertContains('label', $headers);
        }
    }
}
