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
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@symfony/ux-google-map' => [
        'path' => './vendor/symfony/ux-google-map/assets/dist/map_controller.js',
    ],
    '@symfony/ux-live-component' => [
        'path' => './vendor/symfony/ux-live-component/assets/dist/live_controller.js',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    'lodash-es/debounce.js' => [
        'version' => '4.17.22',
    ],
    'lodash-es/throttle.js' => [
        'version' => '4.17.22',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@googlemaps/js-api-loader' => [
        'version' => '1.16.10',
    ],
    'alpinejs' => [
        'version' => '3.15.3',
    ],
    'chart.js' => [
        'version' => '3.9.1',
    ],
    'lodash' => [
        'version' => '4.17.21',
    ],
    '@preline/datepicker' => [
        'version' => '3.2.3',
    ],
    'stimulus-use' => [
        'version' => '0.52.3',
    ],
    'imask' => [
        'version' => '7.6.1',
    ],
    'bazinga-translator' => [
        'version' => '7.0.0',
    ],
    'intl-messageformat' => [
        'version' => '10.7.15',
    ],
    'tslib' => [
        'version' => '2.8.1',
    ],
    '@formatjs/fast-memoize' => [
        'version' => '2.2.6',
    ],
    '@formatjs/icu-messageformat-parser' => [
        'version' => '2.11.1',
    ],
    '@formatjs/icu-skeleton-parser' => [
        'version' => '1.8.13',
    ],
    '@symfony/ux-translator' => [
        'path' => './vendor/symfony/ux-translator/assets/dist/translator_controller.js',
    ],
    'dropzone' => [
        'version' => '6.0.0-beta.2',
    ],
    'just-extend' => [
        'version' => '5.1.1',
    ],
    'cropperjs' => [
        'version' => '2.1.0',
    ],
    '@cropper/utils' => [
        'version' => '2.1.0',
    ],
    '@cropper/elements' => [
        'version' => '2.1.0',
    ],
    '@cropper/element' => [
        'version' => '2.1.0',
    ],
    '@cropper/element-canvas' => [
        'version' => '2.1.0',
    ],
    '@cropper/element-image' => [
        'version' => '2.1.0',
    ],
    '@cropper/element-shade' => [
        'version' => '2.1.0',
    ],
    '@cropper/element-handle' => [
        'version' => '2.1.0',
    ],
    '@cropper/element-selection' => [
        'version' => '2.1.0',
    ],
    '@cropper/element-grid' => [
        'version' => '2.1.0',
    ],
    '@cropper/element-crosshair' => [
        'version' => '2.1.0',
    ],
    '@cropper/element-viewer' => [
        'version' => '2.1.0',
    ],
    'chartjs-plugin-datalabels' => [
        'version' => '2.2.0',
    ],
    'chart.js/helpers' => [
        'version' => '3.9.1',
    ],
    'gaugeJS' => [
        'version' => '1.3.9',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.23',
    ],
    'sortablejs' => [
        'version' => '1.15.7',
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
    'tom-select/dist/css/tom-select.default.min.css' => [
        'version' => '2.6.2',
        'type' => 'css',
    ],
];
