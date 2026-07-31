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
use App\Pim\Api\State\ActiviteCollectionProvider;
use App\Pim\Api\State\ActiviteItemProvider;
use App\Pim\Api\State\ActiviteMediaProcessor;
use App\Pim\Api\State\ActivitePatchProcessor;

#[
    ApiResource(
        shortName: 'Activite',
        description: 'Fiche Activité échangée avec le site externe.',
        formats: ['json' => ['application/json']],
        operations: [
            new GetCollection(
                uriTemplate: '/v1/activites',
                paginationEnabled: false,
                provider: ActiviteCollectionProvider::class,
                openapi: new Operation(
                    tags: ['Activités'],
                    summary: 'Lister les activités',
                    parameters: [
                        new Parameter(
                            'status',
                            'query',
                            'Filtre de statut',
                            schema: ['type' => 'string'],
                        ),
                        new Parameter(
                            'cursor',
                            'query',
                            'Curseur opaque',
                            schema: ['type' => 'string'],
                        ),
                        new Parameter(
                            'limit',
                            'query',
                            'Nombre de résultats',
                            schema: [
                                'type' => 'integer',
                                'minimum' => 1,
                                'maximum' => 100,
                            ],
                        ),
                    ],
                    security: [['bearerAuth' => []]],
                ),
            ),
            new Get(
                uriTemplate: '/v1/activites/{id}',
                requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
                provider: ActiviteItemProvider::class,
                openapi: new Operation(
                    tags: ['Activités'],
                    summary: 'Consulter une activité',
                    security: [['bearerAuth' => []]],
                ),
            ),
            new Patch(
                uriTemplate: '/v1/activites/{id}',
                requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
                input: ActivitePatchInput::class,
                output: self::class,
                inputFormats: ['json' => ['application/merge-patch+json']],
                denormalizationContext: ['allow_extra_attributes' => false],
                read: false,
                processor: ActivitePatchProcessor::class,
                openapi: new Operation(
                    tags: ['Activités'],
                    summary: 'Modifier partiellement une activité',
                    description: 'Conserve le workflow courant.',
                    parameters: [
                        new Parameter(
                            'If-Match',
                            'header',
                            'Version courante',
                            true,
                            schema: ['type' => 'string'],
                        ),
                    ],
                    security: [['bearerAuth' => []]],
                ),
            ),
            new Post(
                uriTemplate: '/v1/activites/{activiteId}/medias',
                uriVariables: [
                    'activiteId' => new Link(
                        fromClass: self::class,
                        identifiers: ['id'],
                        compositeIdentifier: false,
                    ),
                ],
                input: false,
                output: LieuMediaResource::class,
                deserialize: false,
                read: false,
                processor: ActiviteMediaProcessor::class,
                openapi: new Operation(
                    tags: ['Médias Activités'],
                    summary: 'Téléverser une photo',
                    parameters: [
                        new Parameter(
                            'If-Match',
                            'header',
                            'Version courante',
                            true,
                            schema: ['type' => 'string'],
                        ),
                    ],
                    requestBody: new RequestBody(
                        'Photo et métadonnées',
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
                                            'enum' => [
                                                'PHOTO_PRINCIPALE',
                                                'PHOTO_DIVERSE',
                                            ],
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
                uriTemplate: '/v1/activites/{activiteId}/medias/ordre',
                uriVariables: [
                    'activiteId' => new Link(
                        fromClass: self::class,
                        identifiers: ['id'],
                        compositeIdentifier: false,
                    ),
                ],
                input: MediaOrderInput::class,
                output: self::class,
                read: false,
                processor: ActiviteMediaProcessor::class,
                openapi: new Operation(
                    tags: ['Médias Activités'],
                    summary: 'Réordonner les photos',
                    parameters: [
                        new Parameter(
                            'If-Match',
                            'header',
                            'Version courante',
                            true,
                            schema: ['type' => 'string'],
                        ),
                    ],
                    security: [['bearerAuth' => []]],
                ),
            ),
            new Patch(
                uriTemplate: '/v1/activites/{activiteId}/medias/{resourceId}',
                uriVariables: [
                    'activiteId' => new Link(
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
                read: false,
                processor: ActiviteMediaProcessor::class,
                openapi: new Operation(
                    tags: ['Médias Activités'],
                    summary: 'Modifier une photo',
                    parameters: [
                        new Parameter(
                            'If-Match',
                            'header',
                            'Version courante',
                            true,
                            schema: ['type' => 'string'],
                        ),
                    ],
                    security: [['bearerAuth' => []]],
                ),
            ),
            new Post(
                uriTemplate: '/v1/activites/{activiteId}/medias/{resourceId}/fichier',
                uriVariables: [
                    'activiteId' => new Link(
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
                processor: ActiviteMediaProcessor::class,
                openapi: new Operation(
                    tags: ['Médias Activités'],
                    summary: 'Remplacer le fichier photo',
                    parameters: [
                        new Parameter(
                            'If-Match',
                            'header',
                            'Version courante',
                            true,
                            schema: ['type' => 'string'],
                        ),
                    ],
                    requestBody: new RequestBody(
                        'Photo',
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
                uriTemplate: '/v1/activites/{activiteId}/medias/{resourceId}',
                uriVariables: [
                    'activiteId' => new Link(
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
                processor: ActiviteMediaProcessor::class,
                openapi: new Operation(
                    tags: ['Médias Activités'],
                    summary: 'Supprimer une photo',
                    parameters: [
                        new Parameter(
                            'If-Match',
                            'header',
                            'Version courante',
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
final readonly class ActiviteResource
{
    /**
     * @param array{code:string,label:string}|null $prestataire
     * @param list<string>                         $types
     * @param list<string>                         $langues
     * @param list<string>                         $engagementsRse
     * @param list<string>                         $paysMobiles
     * @param list<string>                         $regionsMobiles
     * @param list<string>                         $departementsMobiles
     * @param array<string,mixed>|null             $localisation
     * @param list<string>                         $objectifs
     * @param list<string>                         $plus
     * @param list<array<string,mixed>>            $offres
     * @param list<LieuMediaResource>              $medias
     */
    public function __construct(
        #[ApiProperty(identifier: true)] public string $id,
        public ?int $code,
        public ?string $label,
        public string $status,
        public int $completeness,
        public int $version,
        public ?string $publishedAt,
        public string $updatedAt,
        public ?array $prestataire,
        public array $types,
        public ?string $thematique,
        public array $langues,
        public array $engagementsRse,
        public ?string $modeIntervention,
        public bool $touteFrance,
        public array $paysMobiles,
        public array $regionsMobiles,
        public array $departementsMobiles,
        public ?array $localisation,
        public ?string $descriptionGenerale,
        public ?string $comprendPrestation,
        public array $objectifs,
        public ?int $participantsMin,
        public ?int $participantsMax,
        public ?int $dureeMinMinutes,
        public ?int $dureeMaxMinutes,
        public array $plus,
        public ?string $tarifParPersonne,
        public ?string $youtubeUrl,
        public array $offres,
        public array $medias,
    ) {
    }
}
