<?php

declare(strict_types=1);

namespace App\Pim\Validation;

use App\Dam\Enum\DocumentUsage;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\ModeInterventionService;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\TypeFiche;
use Symfony\Component\Validator\Constraint;

final class ValidServiceEvenementielValidator extends FicheValidateur
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof ServiceEvenementiel) {
            return;
        }

        $this->longueurMax($value->label(), 255, 'label');
        $this->longueurMax($value->youtubeUrl(), 255, 'youtubeUrl');
        $this->lienVideo($value->youtubeUrl(), 'youtubeUrl');

        foreach (
            ['paysMobiles', 'regionsMobiles', 'departementsMobiles'] as $field
        ) {
            foreach ($value->{$field}() as $index => $item) {
                $this->longueurMax(
                    $item,
                    255,
                    sprintf('%s[%d]', $field, $index),
                );
            }
        }

        foreach ($this->tariffs($value) as $field => $amount) {
            if (null !== $amount && (!is_finite($amount) || $amount < 0)) {
                $this->violation(
                    'Le tarif doit être un montant positif ou nul.',
                    $field,
                );
            }
        }

        foreach (
            [
                'participantsMin' => $value->participantsMin(),
                'participantsMax' => $value->participantsMax(),
                'dureeMinutes' => $value->dureeMinutes(),
            ] as $field => $number
        ) {
            if (null !== $number && $number < 0) {
                $this->violation(
                    'Cette valeur doit être un nombre positif.',
                    $field,
                );
            }
        }
        if (
            null !== $value->participantsMin()
            && null !== $value->participantsMax()
            && $value->participantsMin() > $value->participantsMax()
        ) {
            $this->violation(
                'Le minimum de participants doit être inférieur ou égal au maximum.',
                'participantsMin',
            );
        }

        $photos = [];
        foreach ($value->ressources() as $resource) {
            if (null !== $resource->lieu() || null !== $resource->salle()) {
                $this->violation(
                    'Une ressource Service ne peut pas être rattachée à un lieu ou une salle.',
                    'ressources',
                );
            }
            if (NatureRessource::Photo === $resource->nature()) {
                $photos[] = $resource;
            } elseif ('PJ_SUPPORT_COMMERCIAUX' !== $resource->usage()) {
                $this->violation(
                    'Un Service accepte uniquement les supports commerciaux comme documents.',
                    'ressources',
                );
            }
        }

        $this->plafondPhotos(TypeFiche::ServiceEvenementiel, $photos, 'Un Service ne peut pas contenir plus de %d photos.');
        if ($this->enSoumission()) {
            $this->submission($value, $photos);
        }
    }

    /** @param list<RessourceLieu> $photos */
    private function submission(ServiceEvenementiel $value, array $photos): void
    {
        foreach (
            [
                'label' => $value->label(),
                'descriptionGenerale' => $value->descriptionGenerale(),
                'modeIntervention' => $value->modeIntervention(),
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
        if ([] === $value->prestations()) {
            $this->violation(
                'Au moins une prestation est obligatoire.',
                'prestations',
            );
        }

        foreach ($this->booleans($value) as $path => $answer) {
            if (null === $answer) {
                $this->violation(
                    'Une réponse Oui ou Non est obligatoire.',
                    $path,
                );
            }
        }
        foreach ($this->tariffs($value) as $path => $amount) {
            if (null === $amount) {
                $this->violation('Ce tarif en euros est obligatoire.', $path);
            }
        }

        $location = $value->localisation();
        if (ModeInterventionService::Fixe === $value->modeIntervention()) {
            foreach (
                [
                    'pays',
                    'region',
                    'departement',
                    'ruePostale',
                    'codePostal',
                    'ville',
                    'arrondissement',
                    'latitude',
                    'longitude',
                ] as $field
            ) {
                if (
                    null === $location
                    || null === $location->{$field}()
                    || '' === trim((string) $location->{$field}())
                ) {
                    $this->violation(
                        'Ce champ de localisation fixe est obligatoire.',
                        'localisation.'.$field,
                    );
                }
            }
        }
        if (ModeInterventionService::Mobile === $value->modeIntervention()) {
            foreach (
                ['paysMobiles', 'regionsMobiles', 'departementsMobiles'] as $field
            ) {
                if ([] === $value->{$field}()) {
                    $this->violation(
                        'Au moins une valeur est obligatoire.',
                        $field,
                    );
                }
            }
        }

        $this->photosSoumission(TypeFiche::ServiceEvenementiel, $photos);
        $this->ressourcesTraitees($value->ressources());

        $supports = array_values(
            array_filter(
                $value->ressources()->toArray(),
                static fn (
                    RessourceLieu $resource,
                ): bool => NatureRessource::Document === $resource->nature()
                    && DocumentUsage::CommercialSupport ===
                        $resource->documentUsage(),
            ),
        );

        if ([] === $supports) {
            $this->violation(
                'Au moins un support commercial est obligatoire avant soumission.',
                'ressources',
            );
        }

        foreach ($supports as $index => $support) {
            if (
                null === $support->legende()
                || '' === trim($support->legende())
            ) {
                $this->violation(
                    'Le titre du support commercial est obligatoire.',
                    sprintf('ressources[%d].legende', $index),
                );
            }

            if (
                null === $support->source()
                || '' === trim($support->source())
            ) {
                $this->violation(
                    'La source du support commercial est obligatoire.',
                    sprintf('ressources[%d].source', $index),
                );
            }

            if (!$support->rightsGranted()) {
                $this->violation(
                    'Les droits du support commercial doivent être validés.',
                    sprintf('ressources[%d].rightsGranted', $index),
                );
            }
        }
    }

    /** @return array<string, ?bool> */
    private function booleans(ServiceEvenementiel $value): array
    {
        return [
            'prestataireEsat' => $value->prestataireEsat(),
            'demarcheRse' => $value->demarcheRse(),
            'adapteFemmesEnceintes' => $value->adapteFemmesEnceintes(),
            'adapteMalentendants' => $value->adapteMalentendants(),
            'adapteMalvoyants' => $value->adapteMalvoyants(),
            'materielInclus' => $value->materielInclus(),
            'equipementParticipantsRequis' => $value->equipementParticipantsRequis(),
            'equipementReceptionRequis' => $value->equipementReceptionRequis(),
            'contraintesLogistiques' => $value->contraintesLogistiques(),
            'surDevis' => $value->surDevis(),
        ];
    }

    /** @return array<string, ?float> */
    private function tariffs(ServiceEvenementiel $value): array
    {
        return [
            'tarifParPrestation' => $value->tarifParPrestation(),
            'tarifParPersonne' => $value->tarifParPersonne(),
            'tarifParJour' => $value->tarifParJour(),
            'tarifParDemiJournee' => $value->tarifParDemiJournee(),
            'tarifParHeure' => $value->tarifParHeure(),
        ];
    }
}
