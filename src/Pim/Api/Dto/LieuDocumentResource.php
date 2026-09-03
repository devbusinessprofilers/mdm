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
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Pim\Api\State\ActiviteDocumentProcessor;
use App\Pim\Api\State\ActiviteDocumentProvider;
use App\Pim\Api\State\LieuDocumentProcessor;
use App\Pim\Api\State\LieuDocumentProvider;

#[ApiResource(
    shortName: 'LieuDocument',
    operations: [
        new GetCollection(
            uriTemplate: '/v1/lieux/{lieuId}/documents',
            uriVariables: [
                'lieuId' => new Link(
                    fromClass: LieuResource::class,
                    identifiers: ['id'],
                ),
            ],
            paginationEnabled: false,
            provider: LieuDocumentProvider::class,
            openapi: new Operation(
                tags: ['Documents Lieux'],
                summary: 'Lister les documents visibles',
                security: [['bearerAuth' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/v1/lieux/{lieuId}/documents',
            uriVariables: [
                'lieuId' => new Link(
                    fromClass: LieuResource::class,
                    identifiers: ['id'],
                ),
            ],
            input: false,
            deserialize: false,
            read: false,
            processor: LieuDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Lieux'],
                summary: 'Déposer un document',
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
                    'Fichier et métadonnées',
                    new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['document', 'usage'],
                                'properties' => [
                                    'document' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                    ],
                                    'usage' => ['type' => 'string'],
                                    'title' => ['type' => 'string'],
                                    'source' => ['type' => 'string'],
                                    'rightsGranted' => [
                                        'type' => 'boolean',
                                    ],
                                    'salleId' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ]),
                    true,
                ),
                security: [['bearerAuth' => []]],
            ),
        ),
        new Patch(
            uriTemplate: '/v1/lieux/{lieuId}/documents/{documentId}',
            uriVariables: [
                'lieuId' => new Link(
                    fromClass: LieuResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            input: DocumentPatchInput::class,
            read: false,
            processor: LieuDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Lieux'],
                summary: 'Modifier les métadonnées',
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
            uriTemplate: '/v1/lieux/{lieuId}/documents/{documentId}/fichier',
            uriVariables: [
                'lieuId' => new Link(
                    fromClass: LieuResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            input: false,
            deserialize: false,
            read: false,
            processor: LieuDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Lieux'],
                summary: 'Remplacer le fichier',
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
                    'Nouveau fichier',
                    new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['document'],
                                'properties' => [
                                    'document' => [
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
        new Post(
            uriTemplate: '/v1/lieux/{lieuId}/documents/{documentId}/publication',
            uriVariables: [
                'lieuId' => new Link(
                    fromClass: LieuResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            input: DocumentPublicationInput::class,
            read: false,
            processor: LieuDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Lieux'],
                summary: 'Publier ou dépublier',
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
        new Delete(
            uriTemplate: '/v1/lieux/{lieuId}/documents/{documentId}',
            uriVariables: [
                'lieuId' => new Link(
                    fromClass: LieuResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            read: false,
            processor: LieuDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Lieux'],
                summary: 'Supprimer un document',
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
        new Get(
            uriTemplate: '/v1/lieux/{lieuId}/documents/{documentId}/download',
            uriVariables: [
                'lieuId' => new Link(
                    fromClass: LieuResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            provider: LieuDocumentProvider::class,
            openapi: new Operation(
                tags: ['Documents Lieux'],
                summary: 'Obtenir une URL de téléchargement temporaire',
                security: [['bearerAuth' => []]],
            ),
        ),
        new GetCollection(
            uriTemplate: '/v1/activites/{activiteId}/documents',
            uriVariables: [
                'activiteId' => new Link(
                    fromClass: ActiviteResource::class,
                    identifiers: ['id'],
                ),
            ],
            paginationEnabled: false,
            provider: ActiviteDocumentProvider::class,
            openapi: new Operation(
                tags: ['Documents Activités'],
                summary: 'Lister les supports commerciaux visibles',
                security: [['bearerAuth' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/v1/activites/{activiteId}/documents',
            uriVariables: [
                'activiteId' => new Link(
                    fromClass: ActiviteResource::class,
                    identifiers: ['id'],
                ),
            ],
            input: false,
            deserialize: false,
            read: false,
            processor: ActiviteDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Activités'],
                summary: 'Déposer un support commercial',
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
                    'Fichier et métadonnées',
                    new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['document'],
                                'properties' => [
                                    'document' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                    ],
                                    'title' => ['type' => 'string'],
                                    'source' => ['type' => 'string'],
                                    'rightsGranted' => [
                                        'type' => 'boolean',
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
        new Patch(
            uriTemplate: '/v1/activites/{activiteId}/documents/{documentId}',
            uriVariables: [
                'activiteId' => new Link(
                    fromClass: ActiviteResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            input: DocumentPatchInput::class,
            read: false,
            processor: ActiviteDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Activités'],
                summary: 'Modifier les métadonnées',
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
            uriTemplate: '/v1/activites/{activiteId}/documents/{documentId}/fichier',
            uriVariables: [
                'activiteId' => new Link(
                    fromClass: ActiviteResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            input: false,
            deserialize: false,
            read: false,
            processor: ActiviteDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Activités'],
                summary: 'Remplacer le fichier',
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
                    'Nouveau fichier',
                    new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['document'],
                                'properties' => [
                                    'document' => [
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
        new Post(
            uriTemplate: '/v1/activites/{activiteId}/documents/{documentId}/publication',
            uriVariables: [
                'activiteId' => new Link(
                    fromClass: ActiviteResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            input: DocumentPublicationInput::class,
            read: false,
            processor: ActiviteDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Activités'],
                summary: 'Publier ou dépublier',
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
        new Delete(
            uriTemplate: '/v1/activites/{activiteId}/documents/{documentId}',
            uriVariables: [
                'activiteId' => new Link(
                    fromClass: ActiviteResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            read: false,
            processor: ActiviteDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Activités'],
                summary: 'Supprimer un support commercial',
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
        new Get(
            uriTemplate: '/v1/activites/{activiteId}/documents/{documentId}/download',
            uriVariables: [
                'activiteId' => new Link(
                    fromClass: ActiviteResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            provider: ActiviteDocumentProvider::class,
            openapi: new Operation(
                tags: ['Documents Activités'],
                summary: 'Obtenir une URL temporaire',
                security: [['bearerAuth' => []]],
            ),
        ),
    ],
),]
final readonly class LieuDocumentResource
{
    public function __construct(
        #[ApiProperty(identifier: true)] public string $id,
        public int $version,
        public string $damAssetId,
        public string $usage,
        public string $access,
        public string $publicationStatus,
        public ?string $title,
        public ?string $source,
        public bool $rightsGranted,
        public ?string $salleId,
        public ?string $filename,
        public ?string $mimeType,
        public ?int $sizeBytes,
        public ?string $publicUrl,
        public ?string $downloadUrl = null,
        public ?string $keywords = null,
        public ?string $rightsExpiresAt = null,
        public string $rightsValidity = 'not_granted',
    ) {
    }
}
