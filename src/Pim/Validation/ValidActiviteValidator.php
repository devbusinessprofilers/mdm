<?php

declare(strict_types=1);

namespace App\Pim\Validation;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Enum\ModeInterventionActivite;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\TypeFiche;
use App\Pim\Enum\TypeOffreActivite;
use App\Pim\Lov\ActiviteLovCatalog;
use Symfony\Component\Validator\Constraint;

final class ValidActiviteValidator extends FicheValidateur
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof Activite) {
            return;
        }
        $this->longueurMax($value->label(), 255, 'label');
        $this->longueurMax($value->youtubeUrl(), 255, 'youtubeUrl');
        $this->lienVideo($value->youtubeUrl(), 'youtubeUrl');
        foreach ($value->plus() as $index => $plus) {
            $this->longueurMax($plus, 255, sprintf('plus[%d]', $index));
        }
        if (count($value->objectifs()) > 5) {
            $this->violation('Cinq objectifs de séminaire au maximum.', 'objectifs');
        }
        foreach (
            [
                $value->paysMobiles(),
                $value->regionsMobiles(),
                $value->departementsMobiles(),
            ] as $items
        ) {
            foreach ($items as $item) {
                $this->longueurMax($item, 255, 'rayonAction');
            }
        }
        $this->range($value->participantsMin(), 'participantsMin');
        $this->range($value->participantsMax(), 'participantsMax');
        $this->range($value->dureeMinMinutes(), 'dureeMinMinutes');
        $this->range($value->dureeMaxMinutes(), 'dureeMaxMinutes');
        if (
            null !== $value->participantsMin()
            && null !== $value->participantsMax()
            && $value->participantsMax() < $value->participantsMin()
        ) {
            $this->violation(
                'Le maximum de participants doit être supérieur ou égal au minimum.',
                'participantsMax',
            );
        }
        if (
            null !== $value->dureeMinMinutes()
            && null !== $value->dureeMaxMinutes()
            && $value->dureeMaxMinutes() < $value->dureeMinMinutes()
        ) {
            $this->violation(
                'La durée maximale doit être supérieure ou égale à la durée minimale.',
                'dureeMaxMinutes',
            );
        }
        $this->amount($value->tarifParPersonne(), 'tarifParPersonne');
        $counts = [
            TypeOffreActivite::Forfait->value => 0,
            TypeOffreActivite::Option->value => 0,
        ];
        foreach ($value->offres() as $index => $offer) {
            ++$counts[$offer->type()->value];
            $this->range(
                $offer->participantsMin(),
                sprintf('offres[%d].participantsMin', $index),
            );
            $this->range(
                $offer->participantsMax(),
                sprintf('offres[%d].participantsMax', $index),
            );
            $this->amount($offer->prix(), sprintf('offres[%d].prix', $index));
            if (
                null !== $offer->participantsMin()
                && null !== $offer->participantsMax()
                && $offer->participantsMax() < $offer->participantsMin()
            ) {
                $this->violation(
                    'Le maximum doit être supérieur ou égal au minimum.',
                    sprintf('offres[%d].participantsMax', $index),
                );
            }
            if (
                $this->enSoumission()
                && (null === $offer->nom()
                    || null === $offer->prix()
                    || null === $offer->participantsMin()
                    || null === $offer->participantsMax())
            ) {
                $this->violation(
                    'Une offre ajoutée doit être complète.',
                    sprintf('offres[%d]', $index),
                );
            }
        }
        if (
            $counts[TypeOffreActivite::Forfait->value] > 3
            || $counts[TypeOffreActivite::Option->value] > 3
        ) {
            $this->violation(
                'Une activité accepte au maximum trois forfaits et trois options.',
                'offres',
            );
        }
        $photos = [];
        foreach ($value->ressources() as $resource) {
            if (null !== $resource->lieu() || null !== $resource->salle()) {
                $this->violation(
                    'Une ressource Activité ne peut pas être rattachée à un lieu ou une salle.',
                    'ressources',
                );
            }
            if (NatureRessource::Photo === $resource->nature()) {
                $photos[] = $resource;
            } elseif (
                NatureRessource::Document === $resource->nature()
                && 'PJ_SUPPORT_COMMERCIAUX' !== $resource->usage()
                && !\App\Dam\Enum\DocumentUsage::estAdministratif($resource->documentUsage())
            ) {
                $this->violation(
                    'Une Activité accepte uniquement les supports commerciaux et les pièces de facturation comme documents.',
                    'ressources',
                );
            }
        }
        $this->plafondPhotos(TypeFiche::Activite, $photos, 'Une activité ne peut pas contenir plus de %d photos.');
        foreach ($value->sousThematiques() as $sousThematique) {
            try {
                $parent = ActiviteLovCatalog::parentOf($sousThematique);
            } catch (\InvalidArgumentException) {
                $this->violation('Sous-thématique d’activité inconnue.', 'sousThematiques');
                break;
            }
            if (!in_array($parent, $value->thematiques(), true)) {
                $this->violation(
                    'Une sous-thématique nécessite que sa thématique parente soit sélectionnée.',
                    'sousThematiques',
                );
                break;
            }
        }
        if ($this->enSoumission()) {
            $this->submission($value);
        }
    }

    private function submission(Activite $value): void
    {
        foreach (
            [
                [
                    'label',
                    $value->label(),
                    'Le nom de l’activité est obligatoire.',
                ],
                [
                    'prestataire',
                    $value->prestataire(),
                    'Un prestataire issu de la LOV est obligatoire.',
                ],
                [
                    'descriptionGenerale',
                    $value->descriptionGenerale(),
                    'La description générale est obligatoire.',
                ],
            ] as [$path, $field, $message]
        ) {
            if (null === $field || '' === $field) {
                $this->violation($message, $path);
            }
        }
        if ([] === $value->thematiques()) {
            $this->violation(
                'Au moins une thématique est obligatoire.',
                'thematiques',
            );
        }
        if ([] === $value->types()) {
            $this->violation(
                'Au moins un type intérieur ou extérieur est obligatoire.',
                'types',
            );
        }
        if ([] === $value->objectifs()) {
            $this->violation(
                'Au moins un objectif de séminaire est obligatoire.',
                'objectifs',
            );
        }
        if (null === $value->modeIntervention()) {
            $this->violation(
                'Le mode fixe ou mobile est obligatoire.',
                'modeIntervention',
            );
        }
        $fixedLocation = $value->localisation();
        if (
            ModeInterventionActivite::Fixe === $value->modeIntervention()
            && (null === $fixedLocation
                || null === $fixedLocation->ville()
                || '' === trim($fixedLocation->ville()))
        ) {
            $this->violation(
                'Une activité fixe doit renseigner sa ville.',
                'localisation.ville',
            );
        }
        if (
            ModeInterventionActivite::Mobile === $value->modeIntervention()
            && !$value->touteFrance()
            && [] === $value->regionsMobiles()
        ) {
            $this->violation(
                'Une activité mobile doit couvrir au moins une région ou toute la France.',
                'regionsMobiles',
            );
        }
        if (
            null === $value->participantsMin()
            || $value->participantsMin() < 1
            || null === $value->participantsMax()
        ) {
            $this->violation(
                'Les capacités minimum et maximum sont obligatoires et positives.',
                'participantsMin',
            );
        }
        if (
            null === $value->dureeMinMinutes()
            || $value->dureeMinMinutes() < 1
            || null === $value->dureeMaxMinutes()
        ) {
            $this->violation(
                'Les durées minimum et maximum sont obligatoires.',
                'dureeMinMinutes',
            );
        }
        $this->photosSoumission(TypeFiche::Activite, $this->photos($value->ressources()));
        $this->ressourcesTraitees($value->ressources());
    }

    private function range(?int $value, string $path): void
    {
        if (null !== $value && $value < 0) {
            $this->violation('La valeur doit être positive ou nulle.', $path);
        }
    }

    private function amount(?string $value, string $path): void
    {
        if (null !== $value && !preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            $this->violation(
                'Le montant doit être positif ou nul avec deux décimales maximum.',
                $path,
            );
        }
    }
}
