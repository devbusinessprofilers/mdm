<?php

declare(strict_types=1);

namespace App\Pim\Service;

/**
 * Empreintes d'un texte : hash exact du texte normalisé et SimHash 64 bits
 * pour le quasi-doublon. Équivalent texte de PerceptualHashCalculator (DAM) —
 * même format de sortie (16 hex) et même distance de Hamming, pour partager la
 * mécanique de banding et de seuil.
 */
final class TextFingerprintCalculator
{
    private const SHINGLE_SIZE = 3;
    private const POPCOUNT = [0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4];

    /**
     * Normalisation partagée avec la recherche d'adresse (translittération
     * ASCII, minuscules, espaces normalisés) + réduction de la ponctuation à
     * des espaces : deux textes qui ne diffèrent que par la casse, les accents
     * ou la ponctuation ont la même empreinte exacte.
     */
    public function normalize(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = mb_strtolower(false === $ascii ? $value : $ascii);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /** Longueur en caractères du texte normalisé — sert au filtre de longueur minimale. */
    public function length(string $normalized): int
    {
        return mb_strlen($normalized);
    }

    /** SHA-256 hexadécimal du texte normalisé. */
    public function exactHash(string $normalized): string
    {
        return hash('sha256', $normalized);
    }

    /**
     * SimHash 64 bits en hexadécimal 16 caractères. Vote de bits sur des
     * shingles de mots (3-grammes), chacun haché en 64 bits.
     */
    public function simhash(string $normalized): string
    {
        $vector = array_fill(0, 64, 0);
        foreach ($this->shingles($normalized) as $shingle => $weight) {
            $bits = $this->bits64((string) $shingle);
            for ($i = 0; $i < 64; ++$i) {
                $vector[$i] += '1' === $bits[$i] ? $weight : -$weight;
            }
        }

        $bits = '';
        for ($i = 0; $i < 64; ++$i) {
            $bits .= $vector[$i] > 0 ? '1' : '0';
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
            throw new \InvalidArgumentException('Deux SimHash hexadécimaux de 64 bits sont requis.');
        }
        $distance = 0;
        for ($index = 0; $index < 16; ++$index) {
            $distance += self::POPCOUNT[hexdec($left[$index]) ^ hexdec($right[$index])];
        }

        return $distance;
    }

    /**
     * Sac de shingles pondérés par occurrence. Sous SHINGLE_SIZE mots, les
     * mots eux-mêmes servent de jetons pour ne pas laisser un texte court sans
     * empreinte discriminante.
     *
     * @return array<string, int>
     */
    private function shingles(string $normalized): array
    {
        $tokens = '' === $normalized ? [] : explode(' ', $normalized);
        $shingles = [];
        if (count($tokens) < self::SHINGLE_SIZE) {
            foreach ($tokens as $token) {
                $shingles[$token] = ($shingles[$token] ?? 0) + 1;
            }

            return $shingles;
        }
        for ($i = 0, $max = count($tokens) - self::SHINGLE_SIZE; $i <= $max; ++$i) {
            $shingle = implode(' ', array_slice($tokens, $i, self::SHINGLE_SIZE));
            $shingles[$shingle] = ($shingles[$shingle] ?? 0) + 1;
        }

        return $shingles;
    }

    /** 64 bits d'un jeton, dérivés des 16 premiers hexa du SHA-256. */
    private function bits64(string $token): string
    {
        $hex = substr(hash('sha256', $token), 0, 16);
        $bits = '';
        foreach (str_split($hex) as $digit) {
            $bits .= str_pad(decbin((int) hexdec($digit)), 4, '0', STR_PAD_LEFT);
        }

        return $bits;
    }
}
