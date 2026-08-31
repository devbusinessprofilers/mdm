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
use App\Pim\Api\State\ServiceEvenementielCollectionProvider;
use App\Pim\Api\State\ServiceEvenementielItemProvider;
use App\Pim\Api\State\ServiceEvenementielMediaProcessor;
use App\Pim\Api\State\ServiceEvenementielPatchProcessor;

#[
    ApiResource(
        shortName: "ServiceEvenementiel",
        description: "Fiche Service échangée avec le site externe.",
        formats: ["json" => ["application/json"]],
        operations: [
            new GetCollection(
                uriTemplate: "/v1/services",
                paginationEnabled: false,
                provider: ServiceEvenementielCollectionProvider::class,
                openapi: new Operation(
                    tags: ["Services événementiels"],
                    summary: "Lister les services",
                    parameters: [
                        new Parameter(
                            "status",
                            "query",
                            "Filtre de statut",
                            schema: ["type" => "string"],
                        ),
                        new Parameter(
                            "cursor",
                            "query",
                            "Curseur opaque",
                            schema: ["type" => "string"],
                        ),
                        new Parameter(
                            "limit",
                            "query",
                            "Nombre de résultats",
                            schema: [
                                "type" => "integer",
                                "minimum" => 1,
                                "maximum" => 100,
                            ],
                        ),
                    ],
                    security: [["bearerAuth" => []]],
                ),
            ),
            new Get(
                uriTemplate: "/v1/services/{id}",
                requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
                provider: ServiceEvenementielItemProvider::class,
                openapi: new Operation(
                    tags: ["Services événementiels"],
                    summary: "Consulter un service",
                    security: [["bearerAuth" => []]],
                ),
            ),
            new Patch(
                uriTemplate: "/v1/services/{id}",
                requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
                input: ServiceEvenementielPatchInput::class,
                output: self::class,
                inputFormats: ["json" => ["application/merge-patch+json"]],
                denormalizationContext: ["allow_extra_attributes" => false],
                read: false,
                processor: ServiceEvenementielPatchProcessor::class,
                openapi: new Operation(
                    tags: ["Services événementiels"],
                    summary: "Modifier partiellement un service",
                    description: "Conserve le workflow courant.",
                    parameters: [
                        new Parameter(
                            "If-Match",
                            "header",
                            "Version courante",
                            true,
                            schema: ["type" => "string"],
                        ),
                    ],
                    security: [["bearerAuth" => []]],
                ),
            ),
            new Post(
                uriTemplate: "/v1/services/{serviceId}/medias",
                uriVariables: [
                    "serviceId" => new Link(
                        fromClass: self::class,
                        identifiers: ["id"],
                        compositeIdentifier: false,
                    ),
                ],
                input: false,
                output: LieuMediaResource::class,
                deserialize: false,
                read: false,
                processor: ServiceEvenementielMediaProcessor::class,
                openapi: new Operation(
                    tags: ["Médias Services événementiels"],
                    summary: "Téléverser une photo",
                    parameters: [
                        new Parameter(
                            "If-Match",
                            "header",
                            "Version courante",
                            true,
                            schema: ["type" => "string"],
                        ),
                    ],
                    requestBody: new RequestBody(
                        "Photo et métadonnées",
                        new \ArrayObject([
                            "multipart/form-data" => [
                                "schema" => [
                                    "type" => "object",
                                    "required" => ["photo"],
                                    "properties" => [
                                        "photo" => [
                                            "type" => "string",
                                            "format" => "binary",
                                        ],
                                        "usage" => [
                                            "type" => "string",
                                            "enum" => [
                                                "PHOTO_DIVERSE",
                                            ],
                                        ],
                                        "legende" => [
                                            "type" => "string",
                                            "nullable" => true,
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                        true,
                    ),
                    security: [["bearerAuth" => []]],
                ),
            ),
            new Put(
                uriTemplate: "/v1/services/{serviceId}/medias/ordre",
                uriVariables: [
                    "serviceId" => new Link(
                        fromClass: self::class,
                        identifiers: ["id"],
                        compositeIdentifier: false,
                    ),
                ],
                input: MediaOrderInput::class,
                output: self::class,
                read: false,
                processor: ServiceEvenementielMediaProcessor::class,
                openapi: new Operation(
                    tags: ["Médias Services événementiels"],
                    summary: "Réordonner les photos",
                    parameters: [
                        new Parameter(
                            "If-Match",
                            "header",
                            "Version courante",
                            true,
                            schema: ["type" => "string"],
                        ),
                    ],
                    security: [["bearerAuth" => []]],
                ),
            ),
            new Patch(
                uriTemplate: "/v1/services/{serviceId}/medias/{resourceId}",
                uriVariables: [
                    "serviceId" => new Link(
                        fromClass: self::class,
                        identifiers: ["id"],
                        compositeIdentifier: false,
                    ),
                    "resourceId" => new Link(
                        fromClass: self::class,
                        identifiers: ["id"],
                        compositeIdentifier: false,
                    ),
                ],
                input: MediaPatchInput::class,
                output: LieuMediaResource::class,
                inputFormats: ["json" => ["application/merge-patch+json"]],
                read: false,
                processor: ServiceEvenementielMediaProcessor::class,
                openapi: new Operation(
                    tags: ["Médias Services événementiels"],
                    summary: "Modifier une photo",
                    parameters: [
                        new Parameter(
                            "If-Match",
                            "header",
                            "Version courante",
                            true,
                            schema: ["type" => "string"],
                        ),
                    ],
                    security: [["bearerAuth" => []]],
                ),
            ),
            new Post(
                uriTemplate: "/v1/services/{serviceId}/medias/{resourceId}/fichier",
                uriVariables: [
                    "serviceId" => new Link(
                        fromClass: self::class,
                        identifiers: ["id"],
                        compositeIdentifier: false,
                    ),
                    "resourceId" => new Link(
                        fromClass: self::class,
                        identifiers: ["id"],
                        compositeIdentifier: false,
                    ),
                ],
                input: false,
                output: LieuMediaResource::class,
                deserialize: false,
                read: false,
                processor: ServiceEvenementielMediaProcessor::class,
                openapi: new Operation(
                    tags: ["Médias Services événementiels"],
                    summary: "Remplacer le fichier photo",
                    parameters: [
                        new Parameter(
                            "If-Match",
                            "header",
                            "Version courante",
                            true,
                            schema: ["type" => "string"],
                        ),
                    ],
                    requestBody: new RequestBody(
                        "Photo",
                        new \ArrayObject([
                            "multipart/form-data" => [
                                "schema" => [
                                    "type" => "object",
                                    "required" => ["photo"],
                                    "properties" => [
                                        "photo" => [
                                            "type" => "string",
                                            "format" => "binary",
                                        ],
                                    ],
                                ],
                            ],
                        ]),
                        true,
                    ),
                    security: [["bearerAuth" => []]],
                ),
            ),
            new Delete(
                uriTemplate: "/v1/services/{serviceId}/medias/{resourceId}",
                uriVariables: [
                    "serviceId" => new Link(
                        fromClass: self::class,
                        identifiers: ["id"],
                        compositeIdentifier: false,
                    ),
                    "resourceId" => new Link(
                        fromClass: self::class,
                        identifiers: ["id"],
                        compositeIdentifier: false,
                    ),
                ],
                input: false,
                output: false,
                read: false,
                processor: ServiceEvenementielMediaProcessor::class,
                openapi: new Operation(
                    tags: ["Médias Services événementiels"],
                    summary: "Supprimer une photo",
                    parameters: [
                        new Parameter(
                            "If-Match",
                            "header",
                            "Version courante",
                            true,
                            schema: ["type" => "string"],
                        ),
                    ],
                    security: [["bearerAuth" => []]],
                ),
            ),
        ],
    ),
]
final readonly class ServiceEvenementielResource
{
    /**
     * @param list<string>             $prestations
     * @param list<string>             $sousPrestations
     * @param list<string>             $paysMobiles
     * @param list<string>             $regionsMobiles
     * @param list<string>             $departementsMobiles
     * @param array<string,mixed>|null $localisation
     * @param list<LieuMediaResource>  $medias
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
        public array $prestations,
        public array $sousPrestations,
        public ?bool $prestataireEsat,
        public ?bool $demarcheRse,
        public ?string $descriptionGenerale,
        public ?bool $adapteFemmesEnceintes,
        public ?bool $adapteMalentendants,
        public ?bool $adapteMalvoyants,
        public ?bool $materielInclus,
        public ?bool $equipementParticipantsRequis,
        public ?bool $equipementReceptionRequis,
        public ?bool $contraintesLogistiques,
        public ?int $participantsMin,
        public ?int $participantsMax,
        public ?int $dureeMinutes,
        public ?string $modeIntervention,
        public array $paysMobiles,
        public array $regionsMobiles,
        public array $departementsMobiles,
        public ?array $localisation,
        public ?float $tarifParPrestation,
        public ?float $tarifParPersonne,
        public ?float $tarifParJour,
        public ?float $tarifParDemiJournee,
        public ?float $tarifParHeure,
        public ?bool $surDevis,
        public ?string $youtubeUrl,
        public array $medias,
    ) {}
}
