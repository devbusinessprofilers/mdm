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

#[ApiResource(
    shortName: 'RestaurantDocument',
    formats: ['json' => ['application/json']],
    operations: [
        new GetCollection(
            uriTemplate: '/v1/restaurants/{restaurantId}/documents',
            uriVariables: [
                'restaurantId' => new Link(
                    fromClass: RestaurantResource::class,
                    identifiers: ['id'],
                ),
            ],
            output: FicheDocumentResource::class,
            paginationEnabled: false,
            provider: FicheDocumentProvider::class,
            openapi: new Operation(
                tags: ['Documents Restaurants'],
                summary: 'Lister les documents visibles',
                security: [['bearerAuth' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/v1/restaurants/{restaurantId}/documents',
            uriVariables: [
                'restaurantId' => new Link(fromClass: RestaurantResource::class, identifiers: ['id']),
            ],
            input: false,
            output: FicheDocumentResource::class,
            deserialize: false,
            read: false,
            processor: FicheDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Restaurants'],
                summary: 'Déposer un menu, un plan de salle ou un support commercial',
                parameters: [new Parameter('If-Match', 'header', 'Version courante', true, schema: ['type' => 'string'])],
                requestBody: new RequestBody(
                    'Fichier et métadonnées',
                    new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['document', 'usage'],
                                'properties' => [
                                    'document' => ['type' => 'string', 'format' => 'binary'],
                                    'usage' => ['type' => 'string', 'enum' => ['MENUS', 'CONFIG_PLAN_SALLE', 'PJ_SUPPORT_COMMERCIAUX']],
                                    'salleId' => ['type' => 'string', 'nullable' => true],
                                    'title' => ['type' => 'string'],
                                    'source' => ['type' => 'string'],
                                    'rightsGranted' => ['type' => 'boolean'],
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
            uriTemplate: '/v1/restaurants/{restaurantId}/documents/{documentId}',
            uriVariables: [
                'restaurantId' => new Link(fromClass: RestaurantResource::class, identifiers: ['id']),
                'documentId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            input: DocumentPatchInput::class,
            inputFormats: ['json' => ['application/merge-patch+json']],
            output: FicheDocumentResource::class,
            read: false,
            processor: FicheDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Restaurants'],
                summary: 'Modifier les métadonnées',
                parameters: [new Parameter('If-Match', 'header', 'Version courante', true, schema: ['type' => 'string'])],
                security: [['bearerAuth' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/v1/restaurants/{restaurantId}/documents/{documentId}/fichier',
            uriVariables: [
                'restaurantId' => new Link(fromClass: RestaurantResource::class, identifiers: ['id']),
                'documentId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            input: false,
            output: FicheDocumentResource::class,
            deserialize: false,
            read: false,
            processor: FicheDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Restaurants'],
                summary: 'Remplacer le fichier',
                parameters: [new Parameter('If-Match', 'header', 'Version courante', true, schema: ['type' => 'string'])],
                requestBody: new RequestBody(
                    'Nouveau fichier',
                    new \ArrayObject(['multipart/form-data' => ['schema' => ['type' => 'object', 'required' => ['document'], 'properties' => ['document' => ['type' => 'string', 'format' => 'binary']]]]]),
                    true,
                ),
                security: [['bearerAuth' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/v1/restaurants/{restaurantId}/documents/{documentId}/publication',
            uriVariables: [
                'restaurantId' => new Link(fromClass: RestaurantResource::class, identifiers: ['id']),
                'documentId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            input: DocumentPublicationInput::class,
            output: FicheDocumentResource::class,
            read: false,
            processor: FicheDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Restaurants'],
                summary: 'Publier ou dépublier',
                parameters: [new Parameter('If-Match', 'header', 'Version courante', true, schema: ['type' => 'string'])],
                security: [['bearerAuth' => []]],
            ),
        ),
        new Delete(
            uriTemplate: '/v1/restaurants/{restaurantId}/documents/{documentId}',
            uriVariables: [
                'restaurantId' => new Link(fromClass: RestaurantResource::class, identifiers: ['id']),
                'documentId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            output: false,
            read: false,
            processor: FicheDocumentProcessor::class,
            openapi: new Operation(
                tags: ['Documents Restaurants'],
                summary: 'Supprimer un document',
                parameters: [new Parameter('If-Match', 'header', 'Version courante', true, schema: ['type' => 'string'])],
                security: [['bearerAuth' => []]],
            ),
        ),
        new Get(
            uriTemplate: '/v1/restaurants/{restaurantId}/documents/{documentId}/download',
            uriVariables: [
                'restaurantId' => new Link(fromClass: RestaurantResource::class, identifiers: ['id']),
                'documentId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            output: FicheDocumentResource::class,
            provider: FicheDocumentProvider::class,
            openapi: new Operation(
                tags: ['Documents Restaurants'],
                summary: 'Obtenir une URL temporaire',
                security: [['bearerAuth' => []]],
            ),
        ),
    ],
)]
final class RestaurantDocumentResource
{
}
