<?php

declare(strict_types=1);

namespace App\Pim\ReadModel;

use App\Pim\Enum\TriReferentiel;
use Symfony\Component\Uid\Ulid;

/**
 * Curseur de pagination keyset de la liste du référentiel : le tri courant,
 * la valeur de sa clé sur la dernière ligne servie et l'identifiant de
 * départage. La clé est toujours bindée en paramètre SQL, jamais interpolée.
 * Distinct de FicheCursor, propre aux listes API à tri fixe.
 */
final readonly class ReferentielCursor
{
    public function __construct(public TriReferentiel $tri, public string $cle, public Ulid $id)
    {
    }

    public function encode(): string
    {
        return rtrim(strtr(base64_encode(json_encode([
            't' => $this->tri->value,
            'k' => $this->cle,
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
            if (!is_array($data) || !is_string($data['t'] ?? null) || !is_string($data['k'] ?? null) || !is_string($data['i'] ?? null)) {
                throw new \InvalidArgumentException();
            }
            $tri = TriReferentiel::tryFrom($data['t']);
            if (null === $tri || !self::cleValide($tri, $data['k'])) {
                throw new \InvalidArgumentException();
            }

            return new self($tri, $data['k'], Ulid::fromString($data['i']));
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Curseur de pagination invalide.');
        }
    }

    private static function cleValide(TriReferentiel $tri, string $cle): bool
    {
        return match ($tri->colonne()) {
            'modif' => false !== \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $cle),
            'completude', 'diffusion', 'pertinence' => 1 === preg_match('/^-?\d{1,10}$/', $cle),
            default => strlen($cle) <= 255,
        };
    }
}
