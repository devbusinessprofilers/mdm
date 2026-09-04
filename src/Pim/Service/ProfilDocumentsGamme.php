<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Enum\DocumentUsage;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\ActiviteDocumentMetadataType;
use App\Pim\Form\LieuDocumentMetadataType;
use App\Pim\Form\LieuDocumentUploadType;
use App\Pim\Form\RestaurantDocumentMetadataType;
use App\Pim\Form\RestaurantDocumentUploadType;
use Symfony\Component\Form\FormTypeInterface;

/**
 * Ce qui distingue les documents d'une gamme dans l'éditeur : le préfixe des
 * noms de formulaires et des jetons CSRF (inchangés depuis les contrôleurs par
 * gamme), le formulaire de métadonnées, le dépôt direct (Lieu et Restaurant
 * seulement, avec leurs salles) et l'usage imposé (Activité et Service ne
 * connaissent que les supports commerciaux). Tout le reste — actions,
 * réponses, manager — est commun.
 */
final readonly class ProfilDocumentsGamme
{
    /** Onglets du volet Médias du Lieu et les usages proposés par chacun. */
    public const ONGLETS_DEPOT_LIEU = [
        'plans' => [DocumentUsage::RoomPlan, DocumentUsage::GeneralPlan],
        'supports' => [DocumentUsage::CommercialSupport],
        // Les pièces de facturation se déposent dans l'onglet Facturation & partenariat.
        'documents' => [DocumentUsage::RseEvidence],
    ];

    /**
     * @param class-string<FormTypeInterface<mixed>>      $typeMetadata
     * @param class-string<FormTypeInterface<mixed>>|null $typeDepot
     */
    private function __construct(
        public TypeFiche $type,
        private string $prefixe,
        public string $typeMetadata,
        public ?string $typeDepot,
        public ?DocumentUsage $usageImpose,
    ) {
    }

    public static function pour(TypeFiche $type): self
    {
        return match ($type) {
            TypeFiche::Lieu => new self($type, '', LieuDocumentMetadataType::class, LieuDocumentUploadType::class, null),
            TypeFiche::Restaurant => new self($type, 'restaurant', RestaurantDocumentMetadataType::class, RestaurantDocumentUploadType::class, null),
            TypeFiche::Activite => new self($type, 'activite', ActiviteDocumentMetadataType::class, null, DocumentUsage::CommercialSupport),
            TypeFiche::ServiceEvenementiel => new self($type, 'service', ActiviteDocumentMetadataType::class, null, DocumentUsage::CommercialSupport),
            TypeFiche::Traiteur => throw new \InvalidArgumentException('Gamme hors de cette version du MDM.'),
        };
    }

    /** Nom du formulaire d'une action sur un document (`activite_document_metadata_{id}`, `document_delete_{id}` pour le Lieu). */
    public function nomFormulaire(string $action, string $documentId): string
    {
        return $this->prefixeNom('_').'document_'.$action.'_'.$documentId;
    }

    public function jetonCsrf(string $action, string $documentId): string
    {
        return $this->prefixeNom('-').'document-'.$action.'-'.$documentId;
    }

    /**
     * Noms de formulaires de dépôt acceptés, avec les usages proposés par
     * chacun (vide : tous) : un par onglet du volet Médias pour le Lieu, plus
     * le nom historique employé par la matrice des salles.
     *
     * @return array<string, list<DocumentUsage>>
     */
    public function formulairesDepot(): array
    {
        if (TypeFiche::Lieu === $this->type) {
            $formulaires = [];
            foreach (self::ONGLETS_DEPOT_LIEU as $onglet => $usages) {
                $formulaires['document_upload_'.$onglet] = $usages;
            }

            return $formulaires + ['document_upload' => []];
        }

        return [$this->prefixeNom('_').'document_upload' => []];
    }

    /**
     * Options du formulaire de dépôt : salles rattachables et usages proposés
     * (le Restaurant les propose tous).
     *
     * @param list<DocumentUsage> $usages
     *
     * @return array<string, mixed>
     */
    public function optionsDepot(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, array $usages): array
    {
        $options = ['salles' => $this->salles($entite)];
        if (TypeFiche::Lieu === $this->type) {
            $options['usages'] = $usages;
        }

        return $options;
    }

    /**
     * Données initiales du formulaire de métadonnées : les champs communs, plus
     * la salle rattachée (Lieu, Restaurant) et l'usage (Lieu, modifiable).
     *
     * @return array<string, mixed>
     */
    public function donneesMetadata(RessourceLieu $document): array
    {
        $donnees = [
            'title' => $document->legende(), 'source' => $document->source(),
            'keywords' => $document->keywords(), 'rightsExpiresAt' => $document->rightsExpiresAt(),
        ];

        return match ($this->type) {
            TypeFiche::Lieu => ['usage' => $document->documentUsage(), 'salle' => $document->salle()] + $donnees,
            TypeFiche::Restaurant => $donnees + ['salle' => $document->restaurantSalle()],
            default => $donnees,
        };
    }

    /** @return array<string, mixed> */
    public function optionsMetadata(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): array
    {
        return null === $this->typeDepot ? [] : ['salles' => $this->salles($entite)];
    }

    /** @return list<Salle|RestaurantSalle> */
    private function salles(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): array
    {
        return $entite instanceof Lieu || $entite instanceof Restaurant ? array_values($entite->salles()->toArray()) : [];
    }

    private function prefixeNom(string $separateur): string
    {
        return '' === $this->prefixe ? '' : $this->prefixe.$separateur;
    }
}
