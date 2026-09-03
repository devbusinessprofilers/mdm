<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Service\PrivateObjectStorageInterface;
use App\Shared\Service\PublicObjectStorageInterface;

/** Stockage objet en mémoire, pour remplacer les buckets S3 dans le conteneur de test. */
final class StockageMemoire implements PrivateObjectStorageInterface, PublicObjectStorageInterface
{
    /** @param array<string, string> $objets clé => contenu */
    public function __construct(private array $objets = [])
    {
    }

    /** @return list<string> */
    public function cles(): array
    {
        $cles = array_keys($this->objets);
        sort($cles);

        return $cles;
    }

    public function write(string $key, string $contents, array $options = []): void
    {
        $this->objets[$key] = $contents;
    }

    public function writeStream(string $key, mixed $stream, array $options = []): void
    {
        $this->objets[$key] = (string) stream_get_contents($stream);
    }

    public function read(string $key): string
    {
        return $this->objets[$key] ?? throw new \RuntimeException(sprintf('Objet inconnu : %s', $key));
    }

    public function readStream(string $key): mixed
    {
        $stream = fopen('php://temp', 'r+b');
        if (false === $stream) {
            throw new \RuntimeException('Flux temporaire indisponible.');
        }
        fwrite($stream, $this->read($key));
        rewind($stream);

        return $stream;
    }

    public function exists(string $key): bool
    {
        return \array_key_exists($key, $this->objets);
    }

    public function temporaryUrl(string $key, \DateTimeInterface $expiresAt): string
    {
        return 'https://stockage.example.test/'.$key;
    }

    public function delete(string $key): void
    {
        unset($this->objets[$key]);
    }

    public function deleteDirectory(string $prefix): void
    {
        foreach (array_keys($this->objets) as $key) {
            if (str_starts_with($key, rtrim($prefix, '/').'/')) {
                unset($this->objets[$key]);
            }
        }
    }
}
