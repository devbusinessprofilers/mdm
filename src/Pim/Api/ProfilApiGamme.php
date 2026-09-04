<?php

declare(strict_types=1);

namespace App\Pim\Api;

use App\Dam\Enum\DocumentUsage;
use App\Pim\Enum\TypeFiche;

/**
 * Ce qui distingue une gamme dans l'API externe : le nom de sa variable d'URL
 * (`{lieuId}`, `{activiteId}`…, conservé dans les uriTemplate publiés), les
 * catégories de photos et les usages documentaires qu'elle accepte, et si ses
 * photos et plans peuvent se rattacher à une salle. Tout le reste des
 * opérations médias et documents est commun (FicheMediaProcessor,
 * FicheDocumentProcessor, FicheDocumentProvider).
 */
final readonly class ProfilApiGamme
{
    private const CLES_ID = [
        'lieuId' => TypeFiche::Lieu,
        'restaurantId' => TypeFiche::Restaurant,
        'activiteId' => TypeFiche::Activite,
        'serviceId' => TypeFiche::ServiceEvenementiel,
    ];

    /**
     * @param list<string>             $usagesPhotos
     * @param list<DocumentUsage>|null $usagesDocuments null : tous les usages du catalogue
     */
    private function __construct(
        public TypeFiche $type,
        public string $cleId,
        public array $usagesPhotos,
        public ?array $usagesDocuments,
    ) {
    }

    public static function pour(TypeFiche $type): self
    {
        return match ($type) {
            TypeFiche::Lieu => new self($type, 'lieuId', ['PHOTO_FACADE', 'PHOTO_CHAMBRE', 'PHOTO_RESTAURATION', 'CONFIG_PHOTO_SALLE', 'PHOTO_DIVERSE', 'CONFIG_PLAN_SALLE', 'LOISIR_EXTERNE_PHOTO', 'PHOTO'], null),
            TypeFiche::Restaurant => new self($type, 'restaurantId', ['PHOTO_DIVERSE', 'CONFIG_PHOTO_SALLE'], [DocumentUsage::RestaurantMenu, DocumentUsage::RoomPlan, DocumentUsage::CommercialSupport]),
            TypeFiche::Activite => new self($type, 'activiteId', ['PHOTO_DIVERSE'], [DocumentUsage::CommercialSupport]),
            TypeFiche::ServiceEvenementiel => new self($type, 'serviceId', ['PHOTO_DIVERSE'], [DocumentUsage::CommercialSupport]),
            TypeFiche::Traiteur => throw new \InvalidArgumentException('Gamme hors de l’API externe.'),
        };
    }

    /**
     * Gamme d'une opération, reconnue à la variable d'URL portant l'identifiant.
     *
     * @param array<string, mixed> $uriVariables
     */
    public static function depuisUriVariables(array $uriVariables): self
    {
        foreach (self::CLES_ID as $cle => $type) {
            if (\array_key_exists($cle, $uriVariables)) {
                return self::pour($type);
            }
        }
        throw new \LogicException('Opération sans identifiant de fiche (lieuId, restaurantId, activiteId ou serviceId).');
    }

    /** @param array<string, mixed> $uriVariables */
    public function id(array $uriVariables): string
    {
        return (string) ($uriVariables[$this->cleId] ?? '');
    }

    public function photoAutorisee(string $usage): bool
    {
        return in_array($usage, $this->usagesPhotos, true);
    }

    public function documentAutorise(DocumentUsage $usage): bool
    {
        return null === $this->usagesDocuments || in_array($usage, $this->usagesDocuments, true);
    }

    /** Seul usage documentaire possible (Activité, Service), sinon null. */
    public function usageDocumentUnique(): ?DocumentUsage
    {
        return null !== $this->usagesDocuments && 1 === count($this->usagesDocuments) ? $this->usagesDocuments[0] : null;
    }

    /** Les photos de salle et plans de salle se rattachent aux salles du Lieu ou du Restaurant. */
    public function avecSalles(): bool
    {
        return in_array($this->type, [TypeFiche::Lieu, TypeFiche::Restaurant], true);
    }
}
