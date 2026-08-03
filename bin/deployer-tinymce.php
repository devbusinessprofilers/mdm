<?php

/*
 * Recopie la distribution TinyMCE de `vendor/` vers `public/tinymce/`.
 *
 * Le composant `Form:Wysiwyg` du portail monte TinyMCE ; le portail le tire du
 * CDN `cdn.tiny.cloud` avec une clé d'API, on l'auto-héberge ici — pas de
 * credential à porter, pas d'appel tiers. `vendor/` n'étant pas exposé au web,
 * la distribution doit être copiée sous `public/`.
 *
 * Lancé automatiquement après `composer install` et `composer update`.
 * À la main : `composer run tinymce:deploy`.
 */

declare(strict_types=1);

$racine = \dirname(__DIR__);
$source = $racine . '/vendor/tinymce/tinymce';
$cible = $racine . '/public/tinymce';

if (!is_dir($source)) {
    fwrite(\STDERR, "TinyMCE absent de vendor/ : lancer `composer require tinymce/tinymce`.\n");
    exit(1);
}

/** Vide un dossier et le supprime. */
$vider = static function (string $dossier) use (&$vider): void {
    if (!is_dir($dossier)) {
        return;
    }

    foreach (scandir($dossier) ?: [] as $entree) {
        if ('.' === $entree || '..' === $entree) {
            continue;
        }

        $chemin = $dossier . '/' . $entree;
        is_dir($chemin) ? $vider($chemin) : unlink($chemin);
    }

    rmdir($dossier);
};

$vider($cible);
mkdir($cible, 0o777, true);

$parcours = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
);

$fichiers = 0;

foreach ($parcours as $entree) {
    /*
     * Le chemin relatif est recalculé plutôt que lu par `getSubPathName()` :
     * cette méthode appartient à l'itérateur interne et n'est atteinte que par
     * `__call`, ce qu'aucune analyse statique ne sait voir.
     */
    $destination = $cible . '/' . substr($entree->getPathname(), \strlen($source) + 1);

    if ($entree->isDir()) {
        mkdir($destination, 0o777, true);
        continue;
    }

    copy($entree->getPathname(), $destination);
    ++$fichiers;
}

// Métadonnées du paquet : inutiles une fois servies au navigateur.
foreach (['composer.json', 'package.json', 'bower.json', 'CHANGELOG.md', 'README.md'] as $inutile) {
    if (is_file($cible . '/' . $inutile)) {
        unlink($cible . '/' . $inutile);
    }
}

/*
 * Catalogues de langue : le paquet Composer n'en contient aucun, Tiny les
 * distribue à part. Ils sont versionnés dans `resources/tinymce-langs/` et
 * recopiés ici, sinon ce déploiement les effacerait à chaque `composer install`.
 */
$langues = $racine . '/resources/tinymce-langs';
$catalogues = 0;

if (is_dir($langues)) {
    mkdir($cible . '/langs', 0o777, true);

    foreach (glob($langues . '/*.js') ?: [] as $catalogue) {
        copy($catalogue, $cible . '/langs/' . basename($catalogue));
        ++$catalogues;
    }
}

printf(
    "TinyMCE déployé dans public/tinymce/ (%d fichiers, %d catalogue(s) de langue).%s",
    $fichiers,
    $catalogues,
    \PHP_EOL,
);
