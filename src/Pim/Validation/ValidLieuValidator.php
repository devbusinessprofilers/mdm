<?php

declare(strict_types=1);

namespace App\Pim\Validation;

use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\TypeAccesLieu;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\LieuFormCatalog;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\Service\LieuObligationsPublication;
use App\Pim\Service\PhotoObligations;
use Symfony\Component\Validator\Constraint;

final class ValidLieuValidator extends FicheValidateur
{
    public function __construct(
        MediaAssetRepository $assets,
        PhotoObligations $photoObligations,
        private readonly LieuObligationsPublication $obligations,
    ) {
        parent::__construct($assets, $photoObligations);
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof Lieu) {
            return;
        }
        $this->longueurMax(
            $value->label(),
            Lieu::LABEL_MAX_LENGTH,
            'label',
            'Le nom du lieu ne peut pas dépasser 69 caractères.',
        );
        if (
            null !== $value->label()
            && preg_match('/[,"“”«»]/u', $value->label())
        ) {
            $this->violation(
                'Le nom du lieu ne doit contenir ni virgule ni guillemet.',
                'label',
            );
        }
        $this->longueurMax(
            $value->generaleWebsiteUrl(),
            Lieu::WEBSITE_MAX_LENGTH,
            'generaleWebsiteUrl',
            'Le site officiel ne peut pas dépasser 100 caractères.',
        );
        $this->url($value->generaleWebsiteUrl(), 'generaleWebsiteUrl', 'Le site officiel doit être une URL valide.');
        $this->lienVideo($value->generaleYoutube(), 'generaleYoutube');
        $this->longueurMax(
            $value->descGenerale(),
            Lieu::DESCRIPTION_MAX_LENGTH,
            'accessibiliteDescription.descGenerale',
            'La description générale ne peut pas dépasser 1 000 caractères.',
        );
        $this->longueurMax(
            $value->chambreDescGenerale(),
            Lieu::DESCRIPTION_MAX_LENGTH,
            'hebergement.chambreDescGenerale',
            'La description de l’hébergement ne peut pas dépasser 1 000 caractères.',
        );
        $this->longueurMax(
            $value->pmrDetails(),
            Lieu::PMR_DETAILS_MAX_LENGTH,
            'accessibiliteDescription.pmrDetails',
            'Les détails PMR ne peuvent pas dépasser 150 caractères.',
        );
        foreach (['atout1', 'atout2', 'atout3', 'atout4', 'atout5'] as $field) {
            $this->longueurMax(
                $value->{$field}(),
                Lieu::ATOUT_MAX_LENGTH,
                'accessibiliteDescription.'.$field,
                'Un atout ne peut pas dépasser 35 caractères.',
            );
        }
        $this->coordinates($value);
        $this->nonNegativeNumbers($value);
        $this->businessCollections($value);
        $this->resources($value);
        if ($this->enSoumission()) {
            $this->submission($value);
        }
    }

    private function coordinates(Lieu $lieu): void
    {
        $localisation = $lieu->localisation();
        if (null === $localisation) {
            return;
        }
        foreach (
            [
                ['latitude', $localisation->latitude(), -90, 90],
                ['longitude', $localisation->longitude(), -180, 180],
            ] as [$field, $coordinate, $min, $max]
        ) {
            if (null === $coordinate) {
                continue;
            }
            if (
                !preg_match('/^-?\d+(?:\.\d{1,15})?$/', $coordinate)
                || (float) $coordinate < $min
                || (float) $coordinate > $max
            ) {
                $this->violation(
                    sprintf(
                        'La %s doit être comprise entre %d et %d avec au maximum 15 décimales.',
                        $field,
                        $min,
                        $max,
                    ),
                    'localisation.'.$field,
                );
            }
        }
    }

    private function nonNegativeNumbers(Lieu $lieu): void
    {
        $integerFields = array_unique(
            array_merge(
                [
                    'chambreNbTotal',
                    'chambreNbTotalSingle',
                    'chambreNbTotalTwin',
                    'chambreDouble',
                    'chambreCapaciteTotale',
                    'salleReunionNbTotal',
                    'salleReunionCapaciteMaxCocktail',
                    'salleReunionCapaciteMaxTheatre',
                    'salleReunionCapaciteMinTheatre',
                    'salleReunionSurfaceMinReunion',
                    'salleReunionSurfaceMaxReunion',
                    'restaurantTotal',
                    'restaurantSalleRestauration',
                    'restaurantCapaciteDebout',
                    'restaurantCapaciteAssis',
                ],
                array_keys(
                    array_filter(
                        LieuFormCatalog::accommodation() +
                            LieuFormCatalog::meetingRooms() +
                            LieuFormCatalog::restaurant(),
                        static fn (array $definition): bool => !isset(
                            $definition['type'],
                        ),
                    ),
                ),
            ),
        );
        foreach ($integerFields as $field) {
            if (
                method_exists($lieu, $field)
                && is_int($lieu->{$field}())
                && $lieu->{$field}() < 0
            ) {
                $this->violation(
                    'La valeur doit être positive ou nulle.',
                    $field,
                );
            }
        }
        foreach ($lieu->salles() as $index => $salle) {
            foreach (
                [
                    'superficie',
                    'capaciteReunion',
                    'capaciteU',
                    'capaciteClasse',
                    'capaciteTheatre',
                    'capaciteCabaret',
                    'capaciteBanquet',
                    'capaciteCocktail',
                    'capaciteAuditorium',
                    'position',
                ] as $field
            ) {
                $number = $salle->{$field}();
                if (
                    null !== $number
                    && ($number < 0
                        || (str_starts_with($field, 'capacite')
                            && $number > 999999))
                ) {
                    $this->violation(
                        str_starts_with($field, 'capacite')
                            ? 'La capacité doit être comprise entre 0 et 999 999.'
                            : 'La valeur doit être positive ou nulle.',
                        sprintf('salles[%d].%s', $index, $field),
                    );
                }
            }
        }
        foreach (LieuFormCatalog::pricing() as $field => $_definition) {
            $amount = $lieu->tarification()->{$field}();
            if (
                null !== $amount
                && (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)
                    || (float) $amount < 0)
            ) {
                $this->violation(
                    'Le tarif doit être positif ou nul et comporter au maximum deux décimales.',
                    'tarification.'.$field,
                );
            }
        }
    }

    private function businessCollections(Lieu $lieu): void
    {
        $libellesJours = LieuLovCatalog::choicesFor('DISPO_JOUR_OUVERTURE');
        foreach ($lieu->dispoHorairesJours() ?? [] as $jour => $heures) {
            $ouverture = $heures['ouverture'] ?? null;
            $fermeture = $heures['fermeture'] ?? null;
            if (null !== $ouverture && null !== $fermeture && $fermeture <= $ouverture) {
                $this->violation(
                    sprintf("%s : l'heure de fermeture doit être postérieure à l'heure d'ouverture.", $libellesJours[$jour] ?? $jour),
                    'disponibilites.dispoHorairesJours',
                );
            }
        }
        foreach ($lieu->periodesFermeture() as $index => $period) {
            if (
                null !== $period->dateDebut()
                && null !== $period->dateFin()
                && $period->dateFin() < $period->dateDebut()
            ) {
                $this->violation(
                    'La fin de la période doit être postérieure ou égale à son début.',
                    sprintf('periodesFermeture[%d].dateFin', $index),
                );
            }
        }
        $airports = $interests = 0;
        foreach ($lieu->acces() as $access) {
            $airports += TypeAccesLieu::Aeroport === $access->type() ? 1 : 0;
            $interests +=
                TypeAccesLieu::PointInteret === $access->type() ? 1 : 0;
            if (
                ($access->dureeMinutes() ?? 0) < 0
                || $access->position() < 0
                || (null !== $access->distanceKilometres()
                    && (float) $access->distanceKilometres() < 0)
            ) {
                $this->violation(
                    'Les distances, durées et positions doivent être positives ou nulles.',
                    'acces',
                );
            }
        }
        if ($airports > 3) {
            $this->violation(
                'Un lieu ne peut référencer que trois aéroports.',
                'acces',
            );
        }
        if ($interests > 5) {
            $this->violation(
                "Un lieu ne peut référencer que cinq points d'intérêt.",
                'acces',
            );
        }
        if (
            count($lieu->loisirExterneNomPresta()) !==
                count($lieu->loisirExterneNomActivite())
            || count($lieu->loisirExterneNomPresta()) > 8
        ) {
            $this->violation(
                'Les prestataires et activités de team building doivent former au maximum huit couples complets.',
                'loisirs',
            );
        }
    }

    private function resources(Lieu $lieu): void
    {
        $photos = [];
        $documentCounts = [];
        foreach ($lieu->ressources() as $resource) {
            if (
                null !== $resource->salle()
                && $resource->salle()->lieu() !== $lieu
            ) {
                $this->violation(
                    'La salle de la ressource doit appartenir au lieu.',
                    'ressources',
                );
            }
            if (
                in_array(
                    $resource->usage(),
                    ['CONFIG_PHOTO_SALLE', 'CONFIG_PLAN_SALLE'],
                    true,
                )
                && null === $resource->salle()
            ) {
                $this->violation(
                    'Une photo ou un plan de salle doit être rattaché à une salle.',
                    'ressources',
                );
            }
            if (NatureRessource::Photo === $resource->nature()) {
                $photos[] = $resource;
            }
            if (NatureRessource::Document === $resource->nature()) {
                $usage = $resource->documentUsage();
                if (null === $usage) {
                    $this->violation(
                        'L’usage documentaire est invalide.',
                        'ressources',
                    );
                    continue;
                }
                if ($usage->requiresRoom() && null === $resource->salle()) {
                    $this->violation(
                        'Un plan de salle doit être rattaché à une salle.',
                        'ressources',
                    );
                }
                $key =
                    $usage->value.
                    ($usage->requiresRoom()
                        ? ':'.$resource->salle()?->id()
                        : '');
                $documentCounts[$key] = ($documentCounts[$key] ?? 0) + 1;
                if (
                    null !== $usage->maximumCount()
                    && $documentCounts[$key] > $usage->maximumCount()
                ) {
                    $this->violation(
                        'Le nombre maximal de documents pour cet usage est dépassé.',
                        'ressources',
                    );
                }
            }
        }
        $this->plafondPhotos(TypeFiche::Lieu, $photos, 'Un lieu ne peut pas contenir plus de %d photos.');
    }

    private function submission(Lieu $lieu): void
    {
        if (null === $lieu->label() || '' === trim($lieu->label())) {
            $this->violation(
                'Le nom du lieu est obligatoire pour la soumission.',
                'label',
            );
        }
        // Champs « Obligatoire » de la bible VERSION BP : une violation par
        // champ vide, sur le chemin du champ dans le formulaire.
        foreach ($this->obligations->manquants($lieu) as $chemin => $libelle) {
            $this->violation(
                sprintf('« %s » est obligatoire pour la soumission.', $libelle),
                $chemin,
            );
        }
        $this->photosSoumission(TypeFiche::Lieu, $this->photos($lieu->ressources()));
        $this->ressourcesTraitees($lieu->ressources());
    }
}
