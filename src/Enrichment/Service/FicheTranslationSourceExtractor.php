<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\NatureRessource;
use App\Pim\Service\FicheDetailResolver;

final readonly class FicheTranslationSourceExtractor
{
    public function __construct(
        private FicheDetailResolver $details,
    ) {
    }

    /** @return list<TranslationSource> */
    public function extract(Fiche $fiche): array
    {
        return $this->doExtract($fiche, false);
    }

    /** @return list<TranslationSource> */
    public function availableFields(Fiche $fiche): array
    {
        return $this->doExtract($fiche, true);
    }

    /** @return list<TranslationSource> */
    private function doExtract(Fiche $fiche, bool $includeEmpty): array
    {
        $sources = [];
        $this->add($sources, 'nom', 'Nom', $fiche->label(), $includeEmpty);
        $detail = $this->details->pour($fiche);
        if ($detail instanceof Lieu) {
            $this->lieu($sources, $detail, $includeEmpty);
        }
        if ($detail instanceof Activite) {
            $this->activite($sources, $detail, $includeEmpty);
        }
        if ($detail instanceof Restaurant) {
            $this->restaurant($sources, $detail, $includeEmpty);
        }
        if ($detail instanceof ServiceEvenementiel) {
            $this->service($sources, $detail, $includeEmpty);
        }
        foreach ($fiche->resources() as $resource) {
            $this->resource($sources, $resource, $includeEmpty);
        }

        return $sources;
    }

    /** @param list<TranslationSource> $sources */
    private function lieu(array &$sources, Lieu $lieu, bool $includeEmpty): void
    {
        foreach ([
            'pmrDetails' => 'Détails des accès PMR', 'descGenerale' => 'Description générale',
            'descGeneralePointInteret' => "Points d'intérêt", 'atout1' => 'Atout 1', 'atout2' => 'Atout 2',
            'atout3' => 'Atout 3', 'atout4' => 'Atout 4', 'atout5' => 'Atout 5',
            'chambreDescGenerale' => "Description de l'hébergement",
            'salleReunionDescSalleSeminaire' => 'Description des salles de séminaire',
            'rseDescGenerale' => 'Description RSE',
        ] as $field => $label) {
            $this->add($sources, 'lieu.'.$field, $label, $lieu->{$field}(), $includeEmpty);
        }
        $this->stringList($sources, 'lieu.loisirInterne', 'Loisir interne', $lieu->loisirInterne(), $includeEmpty);
        $this->stringList($sources, 'lieu.loisirExterneNomActivite', 'Activité externe', $lieu->loisirExterneNomActivite(), $includeEmpty);
        foreach ($lieu->salles() as $value) {
            $this->add($sources, sprintf('salles[%s].nom', $value->id()), 'Nom de la salle', $value->nom(), $includeEmpty);
        }
        foreach ($lieu->periodesFermeture() as $value) {
            $this->add($sources, sprintf('fermetures[%s].nom', $value->id()), 'Période de fermeture', $value->nom(), $includeEmpty);
        }
        foreach ($lieu->acces() as $value) {
            $this->add($sources, sprintf('acces[%s].nom', $value->id()), "Nom de l'accès", $value->nom(), $includeEmpty);
            $this->add($sources, sprintf('acces[%s].modeTransport', $value->id()), 'Mode de transport', $value->modeTransport(), $includeEmpty);
        }
    }

    /** @param list<TranslationSource> $sources */
    private function activite(array &$sources, Activite $activity, bool $includeEmpty): void
    {
        $this->add($sources, 'activite.descriptionGenerale', 'Description générale', $activity->descriptionGenerale(), $includeEmpty);
        $this->add($sources, 'activite.comprendPrestation', 'Contenu de la prestation', $activity->comprendPrestation(), $includeEmpty);
        $this->stringList($sources, 'activite.plus', 'Atout', $activity->plus(), $includeEmpty);
        foreach ($activity->offres() as $offer) {
            $this->add($sources, sprintf('%ss[%s].nom', $offer->type()->value, $offer->id()), "Nom de l'offre", $offer->nom(), $includeEmpty);
        }
    }

    /** @param list<TranslationSource> $sources */
    private function restaurant(array &$sources, Restaurant $restaurant, bool $includeEmpty): void
    {
        $this->add($sources, 'restaurant.descriptionGenerale', 'Description générale', $restaurant->descriptionGenerale(), $includeEmpty);
        $this->stringList($sources, 'restaurant.atouts', 'Atout', $restaurant->atouts(), $includeEmpty);
        foreach ($restaurant->salles() as $value) {
            $this->add($sources, sprintf('salles[%s].nom', $value->id()), 'Nom de la salle', $value->nom(), $includeEmpty);
        }
        foreach ($restaurant->periodesFermeture() as $value) {
            $this->add($sources, sprintf('fermetures[%s].nom', $value->id()), 'Période de fermeture', $value->nom(), $includeEmpty);
        }
        foreach ($restaurant->acces() as $value) {
            $this->add($sources, sprintf('acces[%s].nom', $value->id()), "Nom de l'accès", $value->nom(), $includeEmpty);
        }
    }

    /** @param list<TranslationSource> $sources */
    private function service(array &$sources, ServiceEvenementiel $service, bool $includeEmpty): void
    {
        $this->add($sources, 'service.descriptionGenerale', 'Description générale', $service->descriptionGenerale(), $includeEmpty);
    }

    /** @param list<TranslationSource> $sources */
    private function resource(array &$sources, RessourceLieu $resource, bool $includeEmpty): void
    {
        $prefix = NatureRessource::Document === $resource->nature() ? 'documents' : 'medias';
        $this->add($sources, sprintf('%s[%s].legende', $prefix, $resource->id()), 'Légende', $resource->legende(), $includeEmpty);
    }

    /** @param list<TranslationSource> $sources */
    private function add(array &$sources, string $path, string $label, ?string $value, bool $includeEmpty = false): void
    {
        $value = null === $value ? '' : trim($value);
        if ($includeEmpty || '' !== $value) {
            $sources[] = new TranslationSource($path, $label, $value);
        }
    }

    /**
     * @param list<TranslationSource> $sources
     * @param list<string>            $values
     */
    private function stringList(array &$sources, string $path, string $label, array $values, bool $includeEmpty = false): void
    {
        foreach ($values as $index => $value) {
            $this->add($sources, sprintf('%s[%d]', $path, $index), $label.' '.($index + 1), $value, $includeEmpty);
        }
    }
}
