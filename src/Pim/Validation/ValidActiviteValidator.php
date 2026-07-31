<?php

declare(strict_types=1);

namespace App\Pim\Validation;

use App\Dam\Enum\MediaStatus;
use App\Dam\Repository\MediaAssetRepository;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\ModeInterventionActivite;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\TypeOffreActivite;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ValidActiviteValidator extends ConstraintValidator
{
    public function __construct(
        private readonly MediaAssetRepository $assets,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!($value instanceof Activite)) {
            return;
        }
        $this->maxLength($value->label(), 255, 'label');
        $this->maxLength($value->youtubeUrl(), 255, 'youtubeUrl');
        if (
            null !== $value->youtubeUrl()
            && false === filter_var($value->youtubeUrl(), FILTER_VALIDATE_URL)
        ) {
            $this->violation(
                'Le lien YouTube doit être une URL valide.',
                'youtubeUrl',
            );
        }
        foreach ($value->plus() as $index => $plus) {
            $this->maxLength($plus, 255, sprintf('plus[%d]', $index));
        }
        foreach (
            [
                $value->paysMobiles(),
                $value->regionsMobiles(),
                $value->departementsMobiles(),
            ] as $items
        ) {
            foreach ($items as $item) {
                $this->maxLength($item, 255, 'rayonAction');
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
                in_array(
                    $this->context->getGroup(),
                    [ValidationGroups::SUBMISSION],
                    true,
                )
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
            ) {
                $this->violation(
                    'Une Activité accepte uniquement les supports commerciaux comme documents.',
                    'ressources',
                );
            }
        }
        if (count($photos) > 10) {
            $this->violation(
                'Une activité ne peut pas contenir plus de dix photos.',
                'ressources',
            );
        }
        if (
            count(
                array_filter(
                    $photos,
                    static fn (
                        RessourceLieu $resource,
                    ): bool => 'PHOTO_PRINCIPALE' === $resource->usage(),
                ),
            ) > 1
        ) {
            $this->violation(
                'Une activité ne peut avoir qu’une seule photo principale.',
                'ressources',
            );
        }
        if (ValidationGroups::SUBMISSION === $this->context->getGroup()) {
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
                    'thematique',
                    $value->thematique(),
                    'La thématique est obligatoire.',
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
        $photos = array_values(
            array_filter(
                $value->ressources()->toArray(),
                static fn (
                    RessourceLieu $resource,
                ): bool => NatureRessource::Photo === $resource->nature(),
            ),
        );
        if (count($photos) < 1 || count($photos) > 10) {
            $this->violation(
                'La soumission exige entre une et dix photos.',
                'ressources',
            );
        }
        if (
            1 !==
            count(
                array_filter(
                    $photos,
                    static fn (
                        RessourceLieu $resource,
                    ): bool => 'PHOTO_PRINCIPALE' === $resource->usage(),
                ),
            )
        ) {
            $this->violation(
                'La soumission exige exactement une photo principale.',
                'ressources',
            );
        }
        foreach ($value->ressources() as $resource) {
            $asset =
                '' === $resource->damAssetId()
                    ? null
                    : $this->assets->find($resource->damAssetId());
            if (
                null === $asset
                || MediaStatus::Processed !== $asset->status()
            ) {
                $this->violation(
                    'Chaque ressource doit posséder un fichier DAM valide et traité.',
                    'ressources',
                );
                break;
            }
        }
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

    private function maxLength(?string $value, int $max, string $path): void
    {
        if (null !== $value && mb_strlen($value) > $max) {
            $this->violation(
                sprintf('La valeur ne peut pas dépasser %d caractères.', $max),
                $path,
            );
        }
    }

    private function violation(string $message, string $path): void
    {
        $this->context->buildViolation($message)->atPath($path)->addViolation();
    }
}
