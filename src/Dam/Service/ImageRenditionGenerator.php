<?php

declare(strict_types=1);

namespace App\Dam\Service;

use Symfony\Component\Process\Process;

final readonly class ImageRenditionGenerator
{
    /**
     * @param resource                                            $original
     * @param array{x: int, y: int, width: int, height: int}|null $crop
     *
     * @return list<GeneratedRendition>
     */
    public function generate(
        mixed $original,
        ?array $crop,
        int $rotation = 0,
    ): array {
        if (!is_resource($original)) {
            throw new \InvalidArgumentException("L'original doit être fourni sous forme de flux.");
        }
        $input = tempnam(sys_get_temp_dir(), 'dam-original-');
        if (false === $input) {
            throw new \RuntimeException('Impossible de créer le fichier temporaire DAM.');
        }
        try {
            $destination = fopen($input, 'wb');
            if (false === $destination) {
                throw new \RuntimeException("Impossible d'écrire le fichier temporaire DAM.");
            }
            try {
                stream_copy_to_stream($original, $destination);
            } finally {
                fclose($destination);
            }
            $renditions = [];
            foreach (ImageVariantRegistry::all() as $name => $dimensions) {
                $output = tempnam(sys_get_temp_dir(), 'dam-rendition-');
                if (false === $output) {
                    throw new \RuntimeException('Impossible de créer le rendu temporaire DAM.');
                }
                try {
                    $command = ['convert', $input, '-auto-orient'];
                    if (null !== $crop) {
                        $command = [
                            ...$command,
                            '-crop',
                            sprintf(
                                '%dx%d+%d+%d',
                                $crop['width'],
                                $crop['height'],
                                $crop['x'],
                                $crop['y'],
                            ),
                            '+repage',
                        ];
                    }
                    if (0 !== $rotation) {
                        $command = [...$command, '-rotate', (string) $rotation];
                    }
                    $geometry =
                        $dimensions['width'].'x'.$dimensions['height'];
                    $command = [
                        ...$command,
                        '-thumbnail',
                        $geometry.'^',
                        '-gravity',
                        'center',
                        '-extent',
                        $geometry,
                        '-strip',
                        '-quality',
                        '82',
                        'webp:'.$output,
                    ];
                    $process = new Process($command);
                    $process->setTimeout(120);
                    $process->mustRun();
                    $contents = file_get_contents($output);
                    if (false === $contents || '' === $contents) {
                        throw new \RuntimeException('ImageMagick a produit un rendu vide.');
                    }
                    $renditions[] = new GeneratedRendition(
                        $name,
                        $dimensions['width'],
                        $dimensions['height'],
                        $contents,
                    );
                } finally {
                    @unlink($output);
                }
            }

            return $renditions;
        } finally {
            @unlink($input);
        }
    }
}
