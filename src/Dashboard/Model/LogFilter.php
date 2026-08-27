<?php

declare(strict_types=1);

namespace App\Dashboard\Model;

use Monolog\Level;
use Symfony\Component\HttpFoundation\Request;

/**
 * Filtres de la visionneuse de logs de /admin/performance, construits depuis
 * la query string avec des bornes sûres. Période par défaut : 24 h.
 */
final readonly class LogFilter
{
    public const PAR_PAGE = 50;
    /** Niveaux proposés par le filtre (valeur Monolog => libellé). */
    public const NIVEAUX = [
        Level::Info->value => 'Info et plus',
        Level::Warning->value => 'Warning et plus',
        Level::Error->value => 'Error et plus',
        Level::Critical->value => 'Critical et plus',
    ];

    public function __construct(
        public int $niveauMin,
        public ?string $canal,
        public string $q,
        public ?\DateTimeImmutable $depuis,
        public ?\DateTimeImmutable $jusqua,
        public int $page,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $niveau = $request->query->getInt('niveau', Level::Info->value);
        $canal = trim($request->query->getString('canal'));
        $depuis = self::parseDate($request->query->getString('depuis'));

        return new self(
            niveauMin: array_key_exists($niveau, self::NIVEAUX) ? $niveau : Level::Info->value,
            canal: '' !== $canal ? $canal : null,
            q: trim($request->query->getString('q')),
            depuis: $depuis ?? new \DateTimeImmutable('-24 hours'),
            jusqua: self::parseDate($request->query->getString('jusqua')),
            page: max(1, $request->query->getInt('page', 1)),
        );
    }

    private static function parseDate(string $value): ?\DateTimeImmutable
    {
        if ('' === trim($value)) {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
