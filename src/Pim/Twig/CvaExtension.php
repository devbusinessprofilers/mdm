<?php

declare(strict_types=1);

namespace App\Pim\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extra\Html\Cva;
use Twig\TwigFunction;

/**
 * Fonction Twig `cva()` du design-system.
 *
 * Les composants repris du portail appellent `cva({ base, variants,
 * compoundVariants, defaultVariants })` — l'API « objet de configuration » de
 * class-variance-authority. Le paquet twig/html-extra expose bien la classe
 * {@see Cva}, mais via une fonction `html_cva(base, variants, …)` à arguments
 * positionnels : passer le hash de configuration en premier argument le
 * placerait dans `base` et n'appliquerait aucune variante.
 *
 * Cette fonction fait le pont : elle accepte le hash unique et instancie `Cva`
 * avec les bons arguments. Les templates restent inchangés.
 */
final class CvaExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cva', $this->cva(...)),
        ];
    }

    /**
     * @param array{
     *     base?: string|list<string>,
     *     variants?: array<string, array<string, string>>,
     *     compoundVariants?: list<array<string, mixed>>,
     *     defaultVariants?: array<string, string>,
     *     defaultVariant?: array<string, string>,
     * } $config
     */
    public function cva(array $config = []): Cva
    {
        return new Cva(
            $config['base'] ?? '',
            $config['variants'] ?? [],
            $config['compoundVariants'] ?? [],
            $config['defaultVariants'] ?? $config['defaultVariant'] ?? [],
        );
    }
}
