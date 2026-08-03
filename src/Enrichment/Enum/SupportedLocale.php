<?php

declare(strict_types=1);

namespace App\Enrichment\Enum;

enum SupportedLocale: string
{
    case Fr = 'fr';
    case En = 'en';
    case Es = 'es';
    case It = 'it';
    case Nl = 'nl';
    case Pt = 'pt';
    case De = 'de';

    /** @return list<self> */
    public static function targets(): array
    {
        return [self::En, self::Es, self::It, self::Nl, self::Pt, self::De];
    }

    public function label(): string
    {
        return match ($this) {
            self::Fr => 'Français',
            self::En => 'Anglais',
            self::Es => 'Espagnol',
            self::It => 'Italien',
            self::Nl => 'Néerlandais',
            self::Pt => 'Portugais',
            self::De => 'Allemand',
        };
    }
}
