<?php

declare(strict_types=1);

namespace App\Pim\Api\Dto;

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
use App\Pim\Api\State\FicheDocumentProcessor;
use App\Pim\Api\State\FicheDocumentProvider;

/**
 * Opérations documentaires des Lieux (`/v1/lieux/{lieuId}/documents…`) :
 * tous les usages du catalogue, plans rattachés aux salles. La
 * représentation est FicheDocumentResource, le traitement FicheDocumentProcessor.
 */
#[ApiResource(
    shortName: 'LieuDocument',
    formats: ['json' => ['application/json']],
    operations: [
        new GetCollection(
            uriTemplate: '/v1/lieux/{lieuId}/documents',
            output: FicheDocumentResource::class,
            uriVariables: [
                'lieuId' => new Link(
                    fromClass: LieuResource::class,
                    identifiers: ['id'],
                ),
            ],
            paginationEnabled: false,
            provider: FicheDocumentProvider::class,
            openapi: new Operation(
                tags: ['Documents Lieux'],
                summary: 'Lister les documents visibles',
                security: [['bearerAuth' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/v1/lieux/{lieuId}/documents',
            output: FicheDocumentResource::class,
            uriVariables: [
                'lieuId' => new Link(
                    fromClass: LieuResource::class,
                    identifiers: ['id'],
                ),
            ],
            input: false,
            deserialize: false,
            read: false,
            processor: FicheDocumentProcessor::class,
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
            output: FicheDocumentResource::class,
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
            inputFormats: ['json' => ['application/merge-patch+json']],
            read: false,
            processor: FicheDocumentProcessor::class,
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
            output: FicheDocumentResource::class,
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
            processor: FicheDocumentProcessor::class,
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
            output: FicheDocumentResource::class,
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
            processor: FicheDocumentProcessor::class,
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
            output: false,
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
            processor: FicheDocumentProcessor::class,
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
            output: FicheDocumentResource::class,
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
            provider: FicheDocumentProvider::class,
            openapi: new Operation(
                tags: ['Documents Lieux'],
                summary: 'Obtenir une URL de téléchargement temporaire',
                security: [['bearerAuth' => []]],
            ),
        ),
    ],
)]
final class LieuDocumentResource
{
}
