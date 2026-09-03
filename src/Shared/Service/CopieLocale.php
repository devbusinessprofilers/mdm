<?php

declare(strict_types=1);

namespace App\Shared\Service;

/**
 * Copie locale temporaire d'un objet du stockage, en flux (un original de
 * 50 Mo ne doit pas transiter par la mémoire du worker). L'appelant supprime
 * le fichier quand il n'en a plus besoin (`supprimer()` dans un `finally`).
 */
final class CopieLocale
{
    /** @return string chemin du fichier temporaire */
    public static function depuis(ObjectStorageInterface $storage, string $key, string $prefixe): string
    {
        $chemin = tempnam(sys_get_temp_dir(), $prefixe);
        if (false === $chemin) {
            throw new \RuntimeException('Impossible de créer la copie locale.');
        }
        try {
            $source = $storage->readStream($key);
            try {
                $destination = fopen($chemin, 'wb');
                if (false === $destination) {
                    throw new \RuntimeException('Impossible d’écrire la copie locale.');
                }
                try {
                    stream_copy_to_stream($source, $destination);
                } finally {
                    fclose($destination);
                }
            } finally {
                if (is_resource($source)) {
                    fclose($source);
                }
            }
        } catch (\Throwable $error) {
            @unlink($chemin);
            throw $error;
        }

        return $chemin;
    }

    public static function supprimer(?string $chemin): void
    {
        if (null !== $chemin && is_file($chemin)) {
            @unlink($chemin);
        }
    }

    private function __construct()
    {
    }
}
