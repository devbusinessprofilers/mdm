<?php

declare(strict_types=1);

namespace App\Pim\Twig;

use App\Pim\Enum\TypeFiche;
use App\Pim\Service\FicheRouteResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `fiche_url(type, id, section)` et `gamme_libelle(type, pluriel)` : les
 * gabarits ne recopient plus ni les noms de routes par gamme ni les tables de
 * libellés.
 */
final class GammeExtension extends AbstractExtension
{
    public function __construct(private readonly FicheRouteResolver $routes)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('fiche_url', $this->ficheUrl(...)),
            new TwigFunction('gamme_libelle', self::gammeLibelle(...)),
        ];
    }

    public function ficheUrl(TypeFiche|string $type, string $id, ?int $section = null): ?string
    {
        $type = is_string($type) ? TypeFiche::tryFrom($type) : $type;

        return null === $type || !$type->estOperationnel() ? null : $this->routes->editUrl($type, $id, $section);
    }

    public static function gammeLibelle(TypeFiche|string $type, bool $pluriel = false): string
    {
        if (is_string($type)) {
            $gamme = TypeFiche::tryFrom($type);
            if (null === $gamme) {
                return $type;
            }
            $type = $gamme;
        }

        return $pluriel ? $type->libellePluriel() : $type->libelle();
    }
}
