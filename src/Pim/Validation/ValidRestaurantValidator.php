<?php

declare(strict_types=1);

namespace App\Pim\Validation;

use App\Dam\Enum\DocumentUsage;
use App\Dam\Enum\MediaStatus;
use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\TypeAccesRestaurant;
use App\Pim\Enum\TypeFiche;
use App\Pim\Lov\RestaurantLovCatalog;
use App\Pim\Service\PhotoObligations;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ValidRestaurantValidator extends ConstraintValidator
{
    public function __construct(
        private readonly MediaAssetRepository $assets,
        private readonly PhotoObligations $photoObligations,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof Restaurant) {
            return;
        }

        $this->maximumLength($value->label(), 255, 'label');
        $this->validUrl($value->siteOfficiel(), Restaurant::WEBSITE_MAX_LENGTH, 'siteOfficiel');
        $this->validUrl($value->youtubeUrl(), 255, 'youtubeUrl');
        if (
            null !== $value->youtubeUrl()
            && !LienVideoValidator::estHebergeurAutorise($value->youtubeUrl())
        ) {
            $this->violation(
                'Le lien vidéo doit pointer vers un hébergeur vidéo reconnu.',
                'youtubeUrl',
            );
        }

        if (count($value->atouts()) > 5) {
            $this->violation('Un Restaurant ne peut avoir que cinq atouts.', 'atouts');
        }

        foreach ($value->atouts() as $index => $atout) {
            $this->maximumLength($atout, 255, sprintf('atouts[%d]', $index));
        }

        foreach ($this->capacities($value) as $path => $capacity) {
            if (null !== $capacity && $capacity < 0) {
                $this->violation('La capacité doit être positive ou nulle.', $path);
            }
        }

        foreach ($value->tarifs() as $path => $tarif) {
            if (null !== $tarif && (!is_numeric($tarif) || (float) $tarif < 0)) {
                $this->violation('Le tarif doit être un montant positif ou nul.', $path);
            }
        }

        $libellesJours = RestaurantLovCatalog::values('DISPO_JOUR_OUVERTURE');
        foreach ($value->horairesJours() ?? [] as $jour => $heures) {
            $ouverture = $heures['ouverture'] ?? null;
            $fermeture = $heures['fermeture'] ?? null;
            if (null !== $ouverture && null !== $fermeture && $fermeture <= $ouverture) {
                $this->violation(
                    sprintf('%s : la fermeture doit être postérieure à l’ouverture.', $libellesJours[$jour] ?? $jour),
                    'horairesJours',
                );
            }
        }

        foreach ($value->periodesFermeture() as $index => $period) {
            $this->maximumLength(
                $period->nom(),
                255,
                sprintf('periodesFermeture[%d].nom', $index),
            );
            if (
                null !== $period->dateDebut()
                && null !== $period->dateFin()
                && $period->dateFin() < $period->dateDebut()
            ) {
                $this->violation(
                    'La fin doit être postérieure ou égale au début.',
                    sprintf('periodesFermeture[%d].dateFin', $index),
                );
            }
        }

        foreach ($value->acces() as $index => $access) {
            $this->maximumLength(
                $access->nom(),
                255,
                sprintf('acces[%d].nom', $index),
            );
            if ($access->position() < 0) {
                $this->violation(
                    'La position doit être positive ou nulle.',
                    sprintf('acces[%d].position', $index),
                );
            }
        }

        foreach ($value->salles() as $index => $room) {
            $this->maximumLength(
                $room->nom(),
                255,
                sprintf('salles[%d].nom', $index),
            );
            foreach (
                [
                    'superficie' => $room->superficie(),
                    'capaciteReunion' => $room->capaciteReunion(),
                    'capaciteU' => $room->capaciteU(),
                    'capaciteClasse' => $room->capaciteClasse(),
                    'capaciteTheatre' => $room->capaciteTheatre(),
                    'capaciteCabaret' => $room->capaciteCabaret(),
                    'capaciteBanquet' => $room->capaciteBanquet(),
                    'capaciteCocktail' => $room->capaciteCocktail(),
                    'capaciteAuditorium' => $room->capaciteAuditorium(),
                    'position' => $room->position(),
                ] as $field => $number
            ) {
                if (null !== $number && $number < 0) {
                    $this->violation(
                        'Cette valeur doit être positive ou nulle.',
                        sprintf('salles[%d].%s', $index, $field),
                    );
                }
            }
        }

        $photos = $this->photos($value);
        $maximum = $this->photoObligations->maximum(TypeFiche::Restaurant);
        if (count($photos) > $maximum) {
            $this->violation(
                sprintf('Un Restaurant ne peut pas contenir plus de %d photos.', $maximum),
                'ressources',
            );
        }

        foreach ($value->ressources() as $resource) {
            if (null !== $resource->lieu() || null !== $resource->salle()) {
                $this->violation(
                    'Une ressource Restaurant ne peut pas être rattachée à un Lieu.',
                    'ressources',
                );
            }

            if (
                null !== $resource->restaurantSalle()
                && $resource->restaurantSalle()->restaurant() !== $value
            ) {
                $this->violation(
                    'La salle de la ressource doit appartenir au Restaurant.',
                    'ressources',
                );
            }

            if (
                DocumentUsage::RoomPlan === $resource->documentUsage()
                && null === $resource->restaurantSalle()
            ) {
                $this->violation(
                    'Un plan de salle Restaurant doit être rattaché à une salle.',
                    'ressources',
                );
            }

            if (
                NatureRessource::Document === $resource->nature()
                && !in_array(
                    $resource->documentUsage(),
                    [
                        DocumentUsage::RoomPlan,
                        DocumentUsage::CommercialSupport,
                        DocumentUsage::RestaurantMenu,
                    ],
                    true,
                )
            ) {
                $this->violation(
                    'Usage documentaire interdit pour un Restaurant.',
                    'ressources',
                );
            }
        }

        if (ValidationGroups::SUBMISSION === $this->context->getGroup()) {
            $this->submission($value, $photos);
        }
    }

    /** @param list<RessourceLieu> $photos */
    private function submission(Restaurant $value, array $photos): void
    {
        foreach (
            [
                'label' => $value->label(),
                'siteOfficiel' => $value->siteOfficiel(),
                'horairesJours' => $value->amplitudeOuverture(),
                'descriptionGenerale' => $value->descriptionGenerale(),
                'capaciteAssiseMax' => $value->capaciteAssiseMax(),
                'capaciteEspacePrivatisable' => $value->capaciteEspacePrivatisable(),
                'capaciteBanquet' => $value->capaciteBanquet(),
                'capaciteCocktail' => $value->capaciteCocktail(),
                'youtubeUrl' => $value->youtubeUrl(),
            ] as $path => $field
        ) {
            if (null === $field || '' === $field) {
                $this->violation(
                    'Ce champ est obligatoire avant soumission.',
                    $path,
                );
            }
        }

        foreach (
            [
                'typesRestaurant' => $value->typesRestaurant(),
                'typesCuisine' => $value->typesCuisine(),
                'specificitesAlimentaires' => $value->specificitesAlimentaires(),
                'typesEvenement' => $value->typesEvenement(),
                'joursOuverture' => $value->joursOuverture(),
                'services' => $value->services(),
                'equipements' => $value->equipements(),
                'engagementsRse' => $value->engagementsRse(),
            ] as $path => $selection
        ) {
            if ([] === $selection) {
                $this->violation(
                    'Au moins une valeur est obligatoire avant soumission.',
                    $path,
                );
            }
        }

        if (5 !== count($value->atouts())) {
            $this->violation(
                'Les cinq atouts sont obligatoires avant soumission.',
                'atouts',
            );
        }

        $location = $value->localisation();
        foreach (
            ['pays', 'region', 'departement', 'ruePostale', 'codePostal', 'ville', 'latitude', 'longitude']
            as $field
        ) {
            if (
                null === $location
                || null === $location->{$field}()
                || '' === trim((string) $location->{$field}())
            ) {
                $this->violation(
                    'Ce champ de localisation est obligatoire.',
                    'localisation.'.$field,
                );
            }
        }

        $accessTypes = array_map(
            static fn ($access): TypeAccesRestaurant => $access->type(),
            $value->acces()->toArray(),
        );
        foreach (
            [TypeAccesRestaurant::Aeroport, TypeAccesRestaurant::Gare]
            as $requiredType
        ) {
            if (!in_array($requiredType, $accessTypes, true)) {
                $this->violation(
                    'Au moins un aéroport et une gare sont obligatoires.',
                    'acces',
                );
                break;
            }
        }

        if (true === $value->privatisationPartielle() && $value->salles()->isEmpty()) {
            $this->violation(
                'Une salle est obligatoire en cas de privatisation partielle.',
                'salles',
            );
        }

        // La principale étant la première photo de l'ordre, la soumission
        // exige toujours au moins une photo même si le minimum est surchargé à 0.
        $minimum = max(1, $this->photoObligations->minimum(TypeFiche::Restaurant));
        $maximum = $this->photoObligations->maximum(TypeFiche::Restaurant);
        if (count($photos) < $minimum || count($photos) > $maximum) {
            $this->violation(
                sprintf('Une fiche Restaurant doit contenir entre %d et %d photos.', $minimum, $maximum),
                'ressources',
            );
        }

        foreach ($value->ressources() as $resource) {
            $asset = $this->assets->find($resource->damAssetId());
            if (null === $asset || MediaStatus::Processed !== $asset->status()) {
                $this->violation(
                    'Chaque ressource doit disposer d’un fichier DAM traité.',
                    'ressources',
                );
            }
        }
    }

    /** @return array<string, ?int> */
    private function capacities(Restaurant $value): array
    {
        return [
            'capaciteAssiseMax' => $value->capaciteAssiseMax(),
            'capaciteEspacePrivatisable' => $value->capaciteEspacePrivatisable(),
            'capaciteBanquet' => $value->capaciteBanquet(),
            'capaciteCocktail' => $value->capaciteCocktail(),
        ];
    }

    /** @return list<RessourceLieu> */
    private function photos(Restaurant $value): array
    {
        return array_values(
            array_filter(
                $value->ressources()->toArray(),
                static fn (RessourceLieu $resource): bool =>
                    NatureRessource::Photo === $resource->nature(),
            ),
        );
    }

    private function validUrl(?string $value, int $maximum, string $path): void
    {
        $this->maximumLength($value, $maximum, $path);
        if (null !== $value && false === filter_var($value, FILTER_VALIDATE_URL)) {
            $this->violation('Cette valeur doit être une URL valide.', $path);
        }
    }

    private function maximumLength(?string $value, int $maximum, string $path): void
    {
        if (null !== $value && mb_strlen($value) > $maximum) {
            $this->violation(
                sprintf('Cette valeur ne doit pas dépasser %d caractères.', $maximum),
                $path,
            );
        }
    }

    private function violation(string $message, string $path): void
    {
        $this->context->buildViolation($message)->atPath($path)->addViolation();
    }
}
