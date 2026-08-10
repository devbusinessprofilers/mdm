<?php

declare(strict_types=1);

namespace App\Pim\Api\Dto;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Pim\Api\State\LieuCollectionProvider;
use App\Pim\Api\State\LieuItemProvider;
use App\Pim\Api\State\LieuMediaProcessor;
use App\Pim\Api\State\LieuPatchProcessor;

#[
    ApiResource(
        shortName: 'Lieu',
        description: 'Fiche Lieu échangée avec le site externe.',
        formats: ['json' => ['application/json']],
        operations: [
            new GetCollection(
                uriTemplate: '/v1/lieux',
                paginationEnabled: false,
                provider: LieuCollectionProvider::class,
                openapi: new Operation(
                    tags: ['Lieux'],
                    summary: 'Lister les lieux',
                    description: 'Pagination par curseur, triée par date de mise à jour décroissante.',
                    parameters: [
                        new Parameter(
                            'status',
                            'query',
                            'Filtre de statut',
                            schema: [
                                'type' => 'string',
                                'enum' => [
                                    'en_cours',
                                    'en_attente_validation',
                                    'validee',
                                    'publiee',
                                    'archivee',
                                ],
                            ],
                        ),
                        new Parameter(
                            'cursor',
                            'query',
                            'Curseur opaque retourné par la page précédente',
                            schema: ['type' => 'string'],
                        ),
                        new Parameter(
                            'limit',
                            'query',
                            'Nombre de résultats, de 1 à 100',
                            schema: [
                                'type' => 'integer',
                                'minimum' => 1,
                                'maximum' => 100,
                                'default' => 50,
                            ],
                        ),
                    ],
                    security: [['bearerAuth' => []]],
                ),
            ),
            new Get(
                uriTemplate: '/v1/lieux/{id}',
                requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
                provider: LieuItemProvider::class,
                openapi: new Operation(
                    tags: ['Lieux'],
                    summary: 'Consulter un lieu',
                    security: [['bearerAuth' => []]],
                ),
            ),
            new Patch(
                uriTemplate: '/v1/lieux/{id}',
                requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
                input: LieuPatchInput::class,
                output: self::class,
                inputFormats: ['json' => ['application/merge-patch+json']],
                denormalizationContext: ['allow_extra_attributes' => false],
                read: false,
                processor: LieuPatchProcessor::class,
                openapi: new Operation(
                    tags: ['Lieux'],
                    summary: 'Modifier partiellement un lieu',
                    description: 'La modification conserve le workflow courant. If-Match doit contenir la version lue.',
                    parameters: [
                        new Parameter(
                            'If-Match',
                            'header',
                            'Version courante, par exemple "3"',
                            true,
                            schema: ['type' => 'string'],
                        ),
                    ],
                    security: [['bearerAuth' => []]],
                ),
            ),
            new Post(
                uriTemplate: '/v1/lieux/{lieuId}/medias',
                uriVariables: [
                    'lieuId' => new Link(
                        fromClass: self::class,
                        identifiers: ['id'],
                        compositeIdentifier: false,
                    ),
                ],
                input: false,
                output: LieuMediaResource::class,
                deserialize: false,
                read: false,
                processor: LieuMediaProcessor::class,
                openapi: new Operation(
                    tags: ['Médias Lieux'],
                    summary: 'Téléverser une image',
                    description: 'Le traitement DAM et les sept variantes sont déclenchés via l’outbox.',
                    parameters: [
                        new Parameter(
                            'If-Match',
                            'header',
                            'Version courante du lieu',
                            true,
                            schema: ['type' => 'string'],
                        ),
                    ],
                    requestBody: new RequestBody(
                        'Fichier image et métadonnées',
                        new \ArrayObject([
                            'multipart/form-data' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['photo'],
                                    'properties' => [
                                        'photo' => [
                                            'type' => 'string',
                                            'format' => 'binary',
                                        ],
                                        'usage' => [
                                            'type' => 'string',
                                            'default' => 'PHOTO_DIVERSE',
                                        ],
                                        'legende' => [
                                            'type' => 'string',
                                            'nullable' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                        true,
                    ),
                    security: [['bearerAuth' => []]],
                ),
            ),
            new Put(
                uriTemplate: '/v1/lieux/{lieuId}/medias/ordre',
                uriVariables: [
                    'lieuId' => new Link(
                        fromClass: self::class,
                        identifiers: ['id'],
                        compositeIdentifier: false,
                    ),
                ],
                input: MediaOrderInput::class,
                output: self::class,
                denormalizationContext: ['allow_extra_attributes' => false],
                read: false,
                processor: LieuMediaProcessor::class,
                openapi: new Operation(
                    tags: ['Médias Lieux'],
                    summary: 'Réordonner toutes les images',
                    parameters: [
                        new Parameter(
                            'If-Match',
                            'header',
                            'Version courante du lieu',
                            true,
                            schema: ['type' => 'string'],
                        ),
                    ],
                    security: [['bearerAuth' => []]],
                ),
            ),
            new Patch(
                uriTemplate: '/v1/lieux/{lieuId}/medias/{resourceId}',
                uriVariables: [
                    'lieuId' => new Link(
                        fromClass: self::class,
                        identifiers: ['id'],
                        compositeIdentifier: false,
                    ),
                    'resourceId' => new Link(
                        fromClass: self::class,
                        identifiers: ['id'],
                        compositeIdentifier: false,
                    ),
                ],
                input: MediaPatchInput::class,
                output: LieuMediaResource::class,
                inputFormats: ['json' => ['application/merge-patch+json']],
                denormalizationContext: ['allow_extra_attributes' => false],
                read: false,
                processor: LieuMediaProcessor::class,
                openapi: new Operation(
                    tags: ['Médias Lieux'],
                    summary: 'Modifier les métadonnées, le recadrage ou la rotation',
                    parameters: [
                        new Parameter(
                            'If-Match',
                            'header',
                            'Version courante du lieu',
                            true,
                            schema: ['type' => 'string'],
                        ),
                    ],
                    security: [['bearerAuth' => []]],
                ),
            ),
            new Post(
                uriTemplate: '/v1/lieux/{lieuId}/medias/{resourceId}/fichier',
                uriVariables: [
                    'lieuId' => new Link(
                        fromClass: self::class,
                        identifiers: ['id'],
                        compositeIdentifier: false,
                    ),
                    'resourceId' => new Link(
                        fromClass: self::class,
                        identifiers: ['id'],
                        compositeIdentifier: false,
                    ),
                ],
                input: false,
                output: LieuMediaResource::class,
                deserialize: false,
                read: false,
                processor: LieuMediaProcessor::class,
                openapi: new Operation(
                    tags: ['Médias Lieux'],
                    summary: 'Remplacer le fichier source',
                    parameters: [
                        new Parameter(
                            'If-Match',
                            'header',
                            'Version courante du lieu',
                            true,
                            schema: ['type' => 'string'],
                        ),
                    ],
                    requestBody: new RequestBody(
                        'Nouveau fichier source',
                        new \ArrayObject([
                            'multipart/form-data' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['photo'],
                                    'properties' => [
                                        'photo' => [
                                            'type' => 'string',
                                            'format' => 'binary',
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                        true,
                    ),
                    security: [['bearerAuth' => []]],
                ),
            ),
            new Delete(
                uriTemplate: '/v1/lieux/{lieuId}/medias/{resourceId}',
                uriVariables: [
                    'lieuId' => new Link(
                        fromClass: self::class,
                        identifiers: ['id'],
                        compositeIdentifier: false,
                    ),
                    'resourceId' => new Link(
                        fromClass: self::class,
                        identifiers: ['id'],
                        compositeIdentifier: false,
                    ),
                ],
                input: false,
                output: false,
                read: false,
                processor: LieuMediaProcessor::class,
                openapi: new Operation(
                    tags: ['Médias Lieux'],
                    summary: 'Supprimer une image',
                    parameters: [
                        new Parameter(
                            'If-Match',
                            'header',
                            'Version courante du lieu',
                            true,
                            schema: ['type' => 'string'],
                        ),
                    ],
                    security: [['bearerAuth' => []]],
                ),
            ),
        ],
    ),
]
final readonly class LieuResource
{
    /**
     * @param list<string>               $generaleTypologie
     * @param array<string, mixed>       $informationsGenerales
     * @param array<string, mixed>       $disponibilites
     * @param array<string, mixed>       $accessibiliteDescription
     * @param array<string, mixed>       $hebergement
     * @param array<string, mixed>       $syntheseSalles
     * @param array<string, mixed>       $equipementsServices
     * @param array<string, mixed>       $rse
     * @param array<string, mixed>       $loisirs
     * @param array<string, mixed>       $restauration
     * @param array<string, mixed>       $visibilite
     * @param array<string, mixed>|null  $localisation
     * @param array<string, mixed>       $administratif
     * @param array<string, mixed>       $tarification
     * @param list<array<string, mixed>> $salles
     * @param list<array<string, mixed>> $periodesFermeture
     * @param list<array<string, mixed>> $acces
     * @param list<LieuMediaResource>    $medias
     */
    public function __construct(
        #[ApiProperty(identifier: true)] public string $id,
        public int $code,
        public ?string $label,
        public string $status,
        public int $completeness,
        /** @var array{marketplace: int, thematicSites: int, salesforce: int, providerPortal: int} */
        public array $completenessByChannel,
        public ?string $completenessCalculatedAt,
        public int $version,
        public ?string $publishedAt,
        public string $updatedAt,
        public array $generaleTypologie,
        public ?string $generaleWebsiteUrl,
        public array $informationsGenerales,
        public array $disponibilites,
        public array $accessibiliteDescription,
        public array $hebergement,
        public array $syntheseSalles,
        public array $equipementsServices,
        public array $rse,
        public array $loisirs,
        public array $restauration,
        public array $visibilite,
        public ?array $localisation,
        public array $administratif,
        public array $tarification,
        public array $salles,
        public array $periodesFermeture,
        public array $acces,
        public array $medias,
    ) {
    }
}
