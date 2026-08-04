<?php

declare(strict_types=1);

namespace App\Tests\Pim\Import;

use App\Pim\Enum\TypeFiche;
use App\Pim\Import\FicheImportTemplateGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FicheImportTemplateGeneratorTest extends KernelTestCase
{
    public function testLieuTemplateContainsHeadersHelpCollectionsAndLovBlock(): void
    {
        self::bootKernel();
        $generator = self::getContainer()->get(FicheImportTemplateGenerator::class);

        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        $generator->write(TypeFiche::Lieu, $stream);
        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        self::assertStringStartsWith("\u{FEFF}", $content);

        $lines = explode("\n", $content);
        self::assertStringStartsWith("\u{FEFF}code;label;localisation_pays", $lines[0]);
        self::assertStringContainsString('salle_1_nom', $lines[0]);
        self::assertStringContainsString('salle_20_climatisee', $lines[0]);
        self::assertStringContainsString('periode_fermeture_10_date_fin', $lines[0]);
        self::assertStringContainsString('acces_10_mode_transport', $lines[0]);
        self::assertStringNotContainsString('salle_21_', $lines[0]);
        self::assertStringStartsWith('#', $lines[1]);
        self::assertStringContainsString('### LISTES DE VALEURS', $content);
        self::assertStringContainsString('# GENERALE_TYPOLOGIE;GENERALE_TYPOLOGIE_1;', $content);

        self::assertSame('modele-import-lieu.csv', $generator->filename(TypeFiche::Lieu));
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
