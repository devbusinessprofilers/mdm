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
use App\Pim\Api\State\ServiceEvenementielDocumentProcessor;
use App\Pim\Api\State\ServiceEvenementielDocumentProvider;

#[ApiResource(
    shortName: 'ServiceDocument',
    operations: [
        new GetCollection(
            uriTemplate: '/v1/services/{serviceId}/documents',
            uriVariables: [
                'serviceId' => new Link(
                    fromClass: ServiceEvenementielResource::class,
                    identifiers: ['id'],
                ),
            ],
            output: LieuDocumentResource::class,
            paginationEnabled: false,
            provider: ServiceEvenementielDocumentProvider::class,
            openapi: new Operation(
                tags: ['Documents Services événementiels'],
                summary: 'Lister les supports commerciaux visibles',
                security: [['bearerAuth' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/v1/services/{serviceId}/documents',
            uriVariables: [
                'serviceId' => new Link(
                    fromClass: ServiceEvenementielResource::class,
                    identifiers: ['id'],
                ),
            ],
            input: false,
            output: LieuDocumentResource::class,
            deserialize: false,
            read: false,
            processor: ServiceEvenementielDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Services événementiels'],
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
            uriTemplate: '/v1/services/{serviceId}/documents/{documentId}',
            uriVariables: [
                'serviceId' => new Link(
                    fromClass: ServiceEvenementielResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            input: DocumentPatchInput::class,
            output: LieuDocumentResource::class,
            read: false,
            processor: ServiceEvenementielDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Services événementiels'],
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
            uriTemplate: '/v1/services/{serviceId}/documents/{documentId}/fichier',
            uriVariables: [
                'serviceId' => new Link(
                    fromClass: ServiceEvenementielResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            input: false,
            output: LieuDocumentResource::class,
            deserialize: false,
            read: false,
            processor: ServiceEvenementielDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Services événementiels'],
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
            uriTemplate: '/v1/services/{serviceId}/documents/{documentId}/publication',
            uriVariables: [
                'serviceId' => new Link(
                    fromClass: ServiceEvenementielResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            input: DocumentPublicationInput::class,
            output: LieuDocumentResource::class,
            read: false,
            processor: ServiceEvenementielDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Services événementiels'],
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
            uriTemplate: '/v1/services/{serviceId}/documents/{documentId}',
            uriVariables: [
                'serviceId' => new Link(
                    fromClass: ServiceEvenementielResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            output: false,
            read: false,
            processor: ServiceEvenementielDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Services événementiels'],
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
            uriTemplate: '/v1/services/{serviceId}/documents/{documentId}/download',
            uriVariables: [
                'serviceId' => new Link(
                    fromClass: ServiceEvenementielResource::class,
                    identifiers: ['id'],
                ),
                'documentId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                ),
            ],
            output: LieuDocumentResource::class,
            provider: ServiceEvenementielDocumentProvider::class,
            openapi: new Operation(
                tags: ['Documents Services événementiels'],
                summary: 'Obtenir une URL temporaire',
                security: [['bearerAuth' => []]],
            ),
        ),
    ],
),]
final class ServiceDocumentResource
{
}
