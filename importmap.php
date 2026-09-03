<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.23',
    ],
    'idiomorph' => [
        'version' => '0.7.4',
    ],
    'frankenphp-hot-reload' => [
        'version' => '1.0.1',
    ],
    'tom-select' => [
        'version' => '2.6.2',
    ],
    '@orchidjs/sifter' => [
        'version' => '1.1.0',
    ],
    '@orchidjs/unicode-variants' => [
        'version' => '1.1.2',
    ],
    'tom-select/dist/css/tom-select.default.css' => [
        'version' => '2.6.2',
        'type' => 'css',
    ],
    'chart.js' => [
        'version' => '4.5.1',
    ],
    '@kurkle/color' => [
        'version' => '0.3.4',
    ],
    'cropperjs' => [
        'version' => '2.2.0',
    ],
    '@cropper/utils' => [
        'version' => '2.2.0',
    ],
    '@cropper/elements' => [
        'version' => '2.2.0',
    ],
    '@cropper/element' => [
        'version' => '2.2.0',
    ],
    '@cropper/element-canvas' => [
        'version' => '2.2.0',
    ],
    '@cropper/element-image' => [
        'version' => '2.2.0',
    ],
    '@cropper/element-shade' => [
        'version' => '2.2.0',
    ],
    '@cropper/element-handle' => [
        'version' => '2.2.0',
    ],
    '@cropper/element-selection' => [
        'version' => '2.2.0',
    ],
    '@cropper/element-grid' => [
        'version' => '2.2.0',
    ],
    '@cropper/element-crosshair' => [
        'version' => '2.2.0',
    ],
    '@cropper/element-viewer' => [
        'version' => '2.2.0',
    ],
];
