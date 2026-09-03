<?php

declare(strict_types=1);

namespace App\Pim\ReadModel;

use Symfony\Component\Uid\Ulid;

final readonly class FicheCursor
{
    public function __construct(public \DateTimeImmutable $updatedAt, public Ulid $id)
    {
    }

    public function encode(): string
    {
        return rtrim(strtr(base64_encode(json_encode([
            'u' => $this->updatedAt->format('Y-m-d H:i:s.u'),
            'i' => (string) $this->id,
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    public static function decode(?string $cursor): ?self
    {
        if (null === $cursor || '' === $cursor) {
            return null;
        }

        try {
            $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
            if (false === $decoded) {
                throw new \InvalidArgumentException();
            }
            $data = json_decode($decoded, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !is_string($data['u'] ?? null) || !is_string($data['i'] ?? null)) {
                throw new \InvalidArgumentException();
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $data['u']);
            if (false === $date) {
                throw new \InvalidArgumentException();
            }

            return new self($date, Ulid::fromString($data['i']));
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Curseur de pagination invalide.');
        }
    }
}
