<?php

declare(strict_types=1);

namespace App\Dam\Service;

use Symfony\Component\Process\Process;

final readonly class PerceptualHashCalculator
{
    private const SIZE = 32;
    private const LOW_FREQUENCIES = 8;
    private const POPCOUNT = [0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4];

    /** @param resource $stream */
    public function calculate(mixed $stream): string
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException("L'original doit être fourni sous forme de flux.");
        }
        $input = tempnam(sys_get_temp_dir(), 'dam-phash-input-');
        $output = tempnam(sys_get_temp_dir(), 'dam-phash-gray-');
        if (false === $input || false === $output) {
            throw new \RuntimeException('Impossible de créer les fichiers temporaires pHash.');
        }
        try {
            $destination = fopen($input, 'wb');
            if (false === $destination) {
                throw new \RuntimeException("Impossible d'écrire le fichier temporaire pHash.");
            }
            try {
                stream_copy_to_stream($stream, $destination);
            } finally {
                fclose($destination);
            }
            // Mémoire ImageMagick bornée : un original démesuré ne doit pas
            // pouvoir mettre le worker en OOM.
            $process = new Process([
                'convert', '-limit', 'memory', '512MiB', '-limit', 'map', '1GiB', $input, '-auto-orient', '-colorspace', 'Gray', '-resize', '32x32!', '-depth', '8', 'gray:'.$output,
            ]);
            $process->setTimeout(60);
            $process->mustRun();
            $contents = file_get_contents($output);
            if (false === $contents || self::SIZE * self::SIZE !== strlen($contents)) {
                throw new \RuntimeException("ImageMagick n'a pas produit la matrice pHash attendue.");
            }

            $pixels = unpack('C*', $contents);
            if (false === $pixels) {
                throw new \RuntimeException("Impossible de lire la matrice pHash produite par ImageMagick.");
            }

            return $this->hash(array_values($pixels));
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }

    /** @param list<int> $pixels */
    public function hash(array $pixels): string
    {
        if (self::SIZE * self::SIZE !== count($pixels)) {
            throw new \InvalidArgumentException('Une matrice de 1024 pixels est requise.');
        }
        $coefficients = [];
        for ($u = 0; $u < self::LOW_FREQUENCIES; ++$u) {
            for ($v = 0; $v < self::LOW_FREQUENCIES; ++$v) {
                $sum = 0.0;
                for ($x = 0; $x < self::SIZE; ++$x) {
                    $cosX = cos(((2 * $x + 1) * $u * M_PI) / (2 * self::SIZE));
                    for ($y = 0; $y < self::SIZE; ++$y) {
                        $sum += $pixels[$x * self::SIZE + $y]
                            * $cosX
                            * cos(((2 * $y + 1) * $v * M_PI) / (2 * self::SIZE));
                    }
                }
                $coefficients[] = $sum;
            }
        }
        $frequencies = array_slice($coefficients, 1);
        sort($frequencies, SORT_NUMERIC);
        $median = $frequencies[intdiv(count($frequencies), 2)];
        $bits = '';
        foreach ($coefficients as $index => $coefficient) {
            $bits .= 0 === $index || $coefficient <= $median ? '0' : '1';
        }
        $hash = '';
        foreach (str_split($bits, 4) as $nibble) {
            $hash .= dechex((int) bindec($nibble));
        }

        return $hash;
    }

    public function distance(string $left, string $right): int
    {
        if (1 !== preg_match('/^[0-9a-f]{16}$/i', $left) || 1 !== preg_match('/^[0-9a-f]{16}$/i', $right)) {
            throw new \InvalidArgumentException('Deux pHash hexadécimaux de 64 bits sont requis.');
        }
        $distance = 0;
        for ($index = 0; $index < 16; ++$index) {
            $distance += self::POPCOUNT[hexdec($left[$index]) ^ hexdec($right[$index])];
        }

        return $distance;
    }
}
