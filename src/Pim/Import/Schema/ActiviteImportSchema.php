<?php

declare(strict_types=1);

namespace App\Pim\Import\Schema;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Activite\OffreActivite;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\ModeInterventionActivite;
use App\Pim\Enum\ModeTarificationActivite;
use App\Pim\Enum\TypeFiche;
use App\Pim\Enum\TypeOffreActivite;
use App\Pim\Lov\ActiviteLovCatalog;
use App\Pim\Repository\ActiviteRepository;

final class ActiviteImportSchema extends AbstractFicheImportSchema
{
    public const MAX_OFFRES = 10;

    private const LOV_ATTRIBUTES = ['TYPE_EXT_INT', 'THEMATIQUE_ACTIVITE', 'LANGUE_PARLEE', 'ENGAGEMENT_RSE', 'OBJECTIF_SEMINAIRE'];

    public function __construct(private readonly ActiviteRepository $activites)
    {
    }

    public function type(): TypeFiche
    {
        return TypeFiche::Activite;
    }

    public function ficheColumns(): array
    {
        return [
            ...$this->commonColumns(),
            new ColumnDefinition('prestataire', ColumnKind::Prestataire, 'prestataire', lovAttribute: 'PRESTATAIRE', help: 'code d’un prestataire existant (LOV PRESTATAIRE)'),
            $this->lovMulti('types', 'TYPE_EXT_INT'),
            $this->lovMulti('thematiques', 'THEMATIQUE_ACTIVITE'),
            $this->lovMulti('sousThematiques', 'SOUS_THEMATIQUE_ACTIVITE'),
            $this->lovMulti('langues', 'LANGUE_PARLEE'),
            $this->lovMulti('engagementsRse', 'ENGAGEMENT_RSE'),
            $this->lovMulti('objectifs', 'OBJECTIF_SEMINAIRE'),
            $this->enum('modeIntervention', ModeInterventionActivite::class),
            $this->bool('touteFrance'),
            $this->list('paysMobiles', 'codes ISO pays séparés par | (FR, BE…)'),
            $this->list('regionsMobiles', 'codes région du référentiel séparés par | (FR-IDF, FR-ARA…)'),
            $this->list('departementsMobiles', 'codes département du référentiel séparés par | (FR-75, FR-78…)'),
            $this->text('descriptionGenerale'),
            $this->text('comprendPrestation'),
            $this->int('participantsMin'),
            $this->int('participantsMax'),
            $this->int('dureeMinMinutes'),
            $this->int('dureeMaxMinutes'),
            $this->list('plus', '5 atouts maximum, séparés par |'),
            $this->decimal('tarifParPersonne'),
            $this->text('youtubeUrl', 255),
        ];
    }

    public function collections(): array
    {
        return [
            new CollectionSchema('offre', self::MAX_OFFRES, OffreActivite::class, 'addOffre', 'offres', [
                new ColumnDefinition('type', ColumnKind::Enum, 'type', enumClass: TypeOffreActivite::class, required: true, nullable: false),
                new ColumnDefinition('nom', ColumnKind::Text, 'nom'),
                new ColumnDefinition('participants_min', ColumnKind::Int, 'participantsMin'),
                new ColumnDefinition('participants_max', ColumnKind::Int, 'participantsMax'),
                new ColumnDefinition('prix', ColumnKind::Decimal, 'prix'),
                new ColumnDefinition('mode_tarification', ColumnKind::Enum, 'modeTarification', enumClass: ModeTarificationActivite::class),
            ]),
        ];
    }

    public function lovChoices(): array
    {
        $choices = [];
        foreach (self::LOV_ATTRIBUTES as $attribute) {
            $choices[$attribute] = ActiviteLovCatalog::choicesFor($attribute);
        }
        // Colonne unique à plat : les codes portent leur thématique, le
        // setter les répartit sur les attributs par famille.
        $choices['SOUS_THEMATIQUE_ACTIVITE'] = ActiviteLovCatalog::sousThematiques();

        return $choices;
    }

    public function createAggregate(): object
    {
        return new Activite();
    }

    public function findAggregateByFiche(Fiche $fiche): ?object
    {
        return $this->activites->findOneByFiche($fiche);
    }

    public function ficheOf(object $aggregate): Fiche
    {
        if (!$aggregate instanceof Activite) {
            throw new \LogicException('Agrégat inattendu pour le type activité.');
        }

        return $aggregate->fiche();
    }
}
