<?php

declare(strict_types=1);

namespace App\Tests\Dam;

use App\Dam\Service\ImageRenditionGenerator;
use App\Dam\Service\ImageVariantRegistry;
use PHPUnit\Framework\TestCase;

final class ImageRenditionGeneratorTest extends TestCase
{
    public function testItCreatesEveryExactWebpVariant(): void
    {
        $process = proc_open(
            [
                'convert',
                '-size',
                '1200x800',
                'gradient:#2563eb-#f8fafc',
                'png:-',
            ],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $source = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process));
        self::assertIsString($source);
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $source);
        rewind($stream);
        $renditions = (new ImageRenditionGenerator())->generate(
            $stream,
            ['x' => 100, 'y' => 100, 'width' => 900, 'height' => 600],
            90,
        );
        fclose($stream);
        self::assertSame(
            ImageVariantRegistry::names(),
            array_map(
                static fn ($rendition): string => $rendition->name,
                $renditions,
            ),
        );
        foreach ($renditions as $rendition) {
            $dimensions = getimagesizefromstring($rendition->contents);
            self::assertIsArray($dimensions);
            self::assertSame(
                ImageVariantRegistry::all()[$rendition->name]['width'],
                $dimensions[0],
            );
            self::assertSame(
                ImageVariantRegistry::all()[$rendition->name]['height'],
                $dimensions[1],
            );
            self::assertSame('image/webp', $dimensions['mime']);
        }
    }

    /**
     * La rotation s'applique avant le rognage : les coordonnées de crop sont
     * exprimées dans l'espace de l'image tournée, comme dans la modale de
     * recadrage. Quadrants colorés, rotation 90° horaire puis crop du quadrant
     * haut-gauche : on doit obtenir le vert (bas-gauche d'origine), pas le
     * rouge (haut-gauche d'origine) qu'aurait produit l'ordre inverse.
     */
    public function testLaRotationSAppliqueAvantLeRognage(): void
    {
        $source = $this->convert([
            'convert', '-size', '200x200', 'xc:white',
            '-fill', '#FF0000', '-draw', 'rectangle 0,0 99,99',
            '-fill', '#0000FF', '-draw', 'rectangle 100,0 199,99',
            '-fill', '#00FF00', '-draw', 'rectangle 0,100 99,199',
            '-fill', '#FFFF00', '-draw', 'rectangle 100,100 199,199',
            'png:-',
        ]);
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $source);
        rewind($stream);
        $renditions = (new ImageRenditionGenerator())->generate(
            $stream,
            ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100],
            90,
        );
        fclose($stream);
        $mean = $this->convert(['convert', '-', '-resize', '1x1!', '-depth', '8', 'txt:-'], $renditions[0]->contents);
        self::assertSame(1, preg_match('/\((\d+),(\d+),(\d+)/', $mean, $rgb), 'Couleur moyenne illisible : '.$mean);
        self::assertLessThan(50, (int) ($rgb[1] ?? 0), 'Canal rouge : '.$mean);
        self::assertGreaterThan(200, (int) ($rgb[2] ?? 0), 'Canal vert : '.$mean);
        self::assertLessThan(50, (int) ($rgb[3] ?? 0), 'Canal bleu : '.$mean);
    }

    /** @param list<string> $command */
    private function convert(array $command, string $input = ''): string
    {
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process));
        self::assertIsString($output);

        return $output;
    }
}
