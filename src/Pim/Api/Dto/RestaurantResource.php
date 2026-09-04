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
use App\Pim\Api\State\RestaurantCollectionProvider;
use App\Pim\Api\State\RestaurantItemProvider;
use App\Pim\Api\State\RestaurantPatchProcessor;
use App\Pim\Api\State\RestaurantMediaProcessor;

#[ApiResource(
    shortName: 'Restaurant',
    description: 'Fiche Restaurant échangée avec le site externe.',
    formats: ['json' => ['application/json']],
    operations: [
        new GetCollection(
            uriTemplate: '/v1/restaurants',
            paginationEnabled: false,
            provider: RestaurantCollectionProvider::class,
            openapi: new Operation(
                tags: ['Restaurants'],
                summary: 'Lister les restaurants',
                parameters: [
                    new Parameter('status', 'query', 'Filtre de statut', schema: ['type' => 'string']),
                    new Parameter('cursor', 'query', 'Curseur opaque', schema: ['type' => 'string']),
                    new Parameter('limit', 'query', 'Nombre de résultats', schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]),
                ],
                security: [['bearerAuth' => []]],
            ),
        ),
        new Get(
            uriTemplate: '/v1/restaurants/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            provider: RestaurantItemProvider::class,
            openapi: new Operation(
                tags: ['Restaurants'],
                summary: 'Consulter un restaurant',
                security: [['bearerAuth' => []]],
            ),
        ),
        new Patch(
            uriTemplate: '/v1/restaurants/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            input: RestaurantPatchInput::class,
            output: self::class,
            inputFormats: ['json' => ['application/merge-patch+json']],
            denormalizationContext: ['allow_extra_attributes' => false],
            read: false,
            processor: RestaurantPatchProcessor::class,
            openapi: new Operation(
                tags: ['Restaurants'],
                summary: 'Modifier partiellement un restaurant',
                description: 'Conserve le workflow courant.',
                parameters: [
                    new Parameter('If-Match', 'header', 'Version courante', true, schema: ['type' => 'string']),
                ],
                security: [['bearerAuth' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/v1/restaurants/{restaurantId}/medias',
            uriVariables: [
                'restaurantId' => new Link(
                    fromClass: self::class,
                    identifiers: ['id'],
                    compositeIdentifier: false,
                ),
            ],
            input: false,
            output: LieuMediaResource::class,
            deserialize: false,
            read: false,
            processor: RestaurantMediaProcessor::class,
            openapi: new Operation(
                tags: ['Médias Restaurants'],
                summary: 'Téléverser une photo',
                parameters: [
                    new Parameter('If-Match', 'header', 'Version courante', true, schema: ['type' => 'string']),
                ],
                requestBody: new RequestBody(
                    'Photo et métadonnées',
                    new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['photo'],
                                'properties' => [
                                    'photo' => ['type' => 'string', 'format' => 'binary'],
                                    'usage' => ['type' => 'string'],
                                    'legende' => ['type' => 'string', 'nullable' => true],
                                    'salleId' => ['type' => 'string', 'nullable' => true],
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
            uriTemplate: '/v1/restaurants/{restaurantId}/medias/ordre',
            uriVariables: [
                'restaurantId' => new Link(fromClass: self::class, identifiers: ['id'], compositeIdentifier: false),
            ],
            input: MediaOrderInput::class,
            output: self::class,
            read: false,
            processor: RestaurantMediaProcessor::class,
            openapi: new Operation(
                tags: ['Médias Restaurants'],
                summary: 'Réordonner les photos',
                parameters: [new Parameter('If-Match', 'header', 'Version courante', true, schema: ['type' => 'string'])],
                security: [['bearerAuth' => []]],
            ),
        ),
        new Patch(
            uriTemplate: '/v1/restaurants/{restaurantId}/medias/{resourceId}',
            uriVariables: [
                'restaurantId' => new Link(fromClass: self::class, identifiers: ['id'], compositeIdentifier: false),
                'resourceId' => new Link(fromClass: self::class, identifiers: ['id'], compositeIdentifier: false),
            ],
            input: MediaPatchInput::class,
            output: LieuMediaResource::class,
            inputFormats: ['json' => ['application/merge-patch+json']],
            read: false,
            processor: RestaurantMediaProcessor::class,
            openapi: new Operation(
                tags: ['Médias Restaurants'],
                summary: 'Modifier une photo',
                parameters: [new Parameter('If-Match', 'header', 'Version courante', true, schema: ['type' => 'string'])],
                security: [['bearerAuth' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/v1/restaurants/{restaurantId}/medias/{resourceId}/fichier',
            uriVariables: [
                'restaurantId' => new Link(fromClass: self::class, identifiers: ['id'], compositeIdentifier: false),
                'resourceId' => new Link(fromClass: self::class, identifiers: ['id'], compositeIdentifier: false),
            ],
            input: false,
            output: LieuMediaResource::class,
            deserialize: false,
            read: false,
            processor: RestaurantMediaProcessor::class,
            openapi: new Operation(
                tags: ['Médias Restaurants'],
                summary: 'Remplacer le fichier photo',
                parameters: [new Parameter('If-Match', 'header', 'Version courante', true, schema: ['type' => 'string'])],
                requestBody: new RequestBody(
                    'Photo',
                    new \ArrayObject(['multipart/form-data' => ['schema' => ['type' => 'object', 'required' => ['photo'], 'properties' => ['photo' => ['type' => 'string', 'format' => 'binary']]]]]),
                    true,
                ),
                security: [['bearerAuth' => []]],
            ),
        ),
        new Delete(
            uriTemplate: '/v1/restaurants/{restaurantId}/medias/{resourceId}',
            uriVariables: [
                'restaurantId' => new Link(fromClass: self::class, identifiers: ['id'], compositeIdentifier: false),
                'resourceId' => new Link(fromClass: self::class, identifiers: ['id'], compositeIdentifier: false),
            ],
            input: false,
            output: false,
            read: false,
            processor: RestaurantMediaProcessor::class,
            openapi: new Operation(
                tags: ['Médias Restaurants'],
                summary: 'Supprimer une photo',
                parameters: [new Parameter('If-Match', 'header', 'Version courante', true, schema: ['type' => 'string'])],
                security: [['bearerAuth' => []]],
            ),
        ),
    ],
)]
final readonly class RestaurantResource
{
    /**
     * @param list<string> $typesRestaurant
     * @param list<string> $typesCuisine
     * @param list<string> $specificitesAlimentaires
     * @param list<string> $typesEvenement
     * @param list<string> $joursOuverture
     * @param list<array<string, mixed>> $periodesFermeture
     * @param array<string, mixed>|null $localisation
     * @param list<array<string, mixed>> $acces
     * @param list<string> $atouts
     * @param list<array<string, mixed>> $salles
     * @param list<string> $services
     * @param list<string> $equipements
     * @param list<string> $engagementsRse
     * @param list<LieuMediaResource> $medias
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
        public array $typesRestaurant,
        public array $typesCuisine,
        public array $specificitesAlimentaires,
        public array $typesEvenement,
        public ?string $siteOfficiel,
        public ?bool $privatisationTotale,
        public ?bool $privatisationPartielle,
        public array $joursOuverture,
        /** Amplitude dérivée des horaires par jour (rétrocompat portail). */
        public ?string $heureOuverture,
        public ?string $heureFermeture,
        /** @var array<string, array{ouverture: ?string, fermeture: ?string}>|null */
        public ?array $horairesJours,
        public array $periodesFermeture,
        public ?array $localisation,
        public array $acces,
        public ?bool $accesPmr,
        public ?bool $toilettesPmr,
        public ?string $descriptionGenerale,
        public array $atouts,
        public ?int $capaciteAssiseMax,
        public ?int $capaciteEspacePrivatisable,
        public ?int $capaciteBanquet,
        public ?int $capaciteCocktail,
        public array $salles,
        public array $services,
        public array $equipements,
        public array $engagementsRse,
        public ?string $youtubeUrl,
        public array $medias,
        /** Onglet Tarifs : montants HT « à partir de » (chaîne décimale), null = non proposé. */
        public ?string $tarifDejeunerAssis = null,
        public ?string $tarifCocktailDejeunatoire = null,
        public ?string $tarifDinerAssis = null,
        public ?string $tarifCocktailDinatoire = null,
        public ?string $tarifForfaitVin = null,
        public ?string $tarifForfaitAlcool = null,
    ) {
    }
}
