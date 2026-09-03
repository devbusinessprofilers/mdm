<?php

declare(strict_types=1);

/*
 * Style du code : jeu @Symfony, sans règle « risquée » (aucune règle qui
 * change le comportement : pas d'ajout de declare(strict_types), pas de
 * réécriture des appels natifs). Périmètre : src/ et tests/.
 *
 * Vérification : vendor/bin/php-cs-fixer fix --dry-run --diff
 * Application  : vendor/bin/php-cs-fixer fix
 */

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests'])
    ->exclude(['var']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@Symfony' => true,
        // Le projet aligne ses tableaux et ses arguments sur une virgule finale.
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        // Les commentaires métier en français gardent leur ponctuation libre.
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,
        // Un docblock d'une ligne reste lisible sur une ligne.
        'phpdoc_line_span' => false,
        // Ordre des `use` : par nom, insensible à la casse.
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'global_namespace_import' => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
        // Pas de réécriture yoda : le code lit `null === $x` et `$x === null` indifféremment.
        'yoda_style' => false,
        // Les accolades de contrôle sur une ligne (`if (...) { return; }`) sont
        // explosées : c'est le but de la passe, pas une exception.
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/var/.php-cs-fixer.cache');
