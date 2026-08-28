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
            $variants = ImageVariantRegistry::all();
            $outputs = [];
            try {
                foreach (array_keys($variants) as $name) {
                    $output = tempnam(sys_get_temp_dir(), 'dam-rendition-');
                    if (false === $output) {
                        throw new \RuntimeException('Impossible de créer le rendu temporaire DAM.');
                    }
                    $outputs[$name] = $output;
                }
                // Mémoire ImageMagick bornée : un original démesuré ne doit
                // pas pouvoir mettre le worker en OOM.
                $command = [
                    'convert',
                    '-limit', 'memory', '512MiB',
                    '-limit', 'map', '1GiB',
                    $input,
                    '-auto-orient',
                ];
                // La rotation s'applique avant le rognage : les coordonnées de
                // crop stockées sont exprimées dans l'espace de l'image après
                // auto-orient + rotation, telle que l'utilisateur la voit dans
                // la modale de recadrage.
                if (0 !== $rotation) {
                    $command = [...$command, '-rotate', (string) $rotation, '+repage'];
                }
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
                // L'original n'est décodé qu'une fois : chaque variante est
                // produite à partir d'un clone de l'image préparée, la
                // dernière consommant l'image de base elle-même.
                $command = [...$command, '-strip', '-quality', '82', '-gravity', 'center'];
                $names = array_keys($variants);
                $last = array_key_last($names);
                foreach ($names as $index => $name) {
                    $geometry = $variants[$name]['width'].'x'.$variants[$name]['height'];
                    $steps = ['-thumbnail', $geometry.'^', '-extent', $geometry];
                    $command = $index === $last
                        ? [...$command, ...$steps, 'webp:'.$outputs[$name]]
                        : [...$command, '(', '+clone', ...$steps, '-write', 'webp:'.$outputs[$name], '+delete', ')'];
                }
                $process = new Process($command);
                $process->setTimeout(120);
                $process->mustRun();
                $renditions = [];
                foreach ($variants as $name => $dimensions) {
                    $contents = file_get_contents($outputs[$name]);
                    if (false === $contents || '' === $contents) {
                        throw new \RuntimeException('ImageMagick a produit un rendu vide.');
                    }
                    $renditions[] = new GeneratedRendition(
                        $name,
                        $dimensions['width'],
                        $dimensions['height'],
                        $contents,
                    );
                }

                return $renditions;
            } finally {
                foreach ($outputs as $output) {
                    @unlink($output);
                }
            }
        } finally {
            @unlink($input);
        }
    }
}
