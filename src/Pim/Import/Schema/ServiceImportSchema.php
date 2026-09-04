<?php

declare(strict_types=1);

namespace App\Pim\Import\Schema;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Service\ServiceAcces;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\ModeInterventionService;
use App\Pim\Enum\TypeAccesService;
use App\Pim\Enum\TypeFiche;
use App\Pim\Lov\ServiceLovCatalog;
use App\Pim\Repository\ServiceEvenementielRepository;

final class ServiceImportSchema extends AbstractFicheImportSchema
{
    public const MAX_ACCES = 12;

    public function __construct(private readonly ServiceEvenementielRepository $services)
    {
    }

    public function type(): TypeFiche
    {
        return TypeFiche::ServiceEvenementiel;
    }

    public function ficheColumns(): array
    {
        return [
            ...$this->commonColumns(),
            $this->lovMulti('prestations', 'TYPE_PRESTATAIRE'),
            $this->lovMulti('sousPrestations', 'SOUS_PRESTATION'),
            $this->text('descriptionGenerale'),
            $this->boolNull('prestataireEsat'),
            $this->boolNull('demarcheRse'),
            $this->boolNull('adapteFemmesEnceintes'),
            $this->boolNull('adapteMalentendants'),
            $this->boolNull('adapteMalvoyants'),
            $this->boolNull('materielInclus'),
            $this->boolNull('equipementParticipantsRequis'),
            $this->boolNull('equipementReceptionRequis'),
            $this->boolNull('contraintesLogistiques'),
            $this->int('participantsMin'),
            $this->int('participantsMax'),
            $this->int('dureeMinutes'),
            $this->enum('modeIntervention', ModeInterventionService::class),
            $this->list('paysMobiles', 'codes ISO pays séparés par | (FR, BE…)'),
            $this->list('regionsMobiles', 'codes région du référentiel séparés par | (FR-IDF, FR-ARA…)'),
            $this->list('departementsMobiles', 'codes département du référentiel séparés par | (FR-75, FR-78…)'),
            $this->boolNull('accesPmr'),
            $this->boolNull('materielAdaptePmr'),
            $this->float('tarifParPrestation'),
            $this->float('tarifParPersonne'),
            $this->float('tarifParJour'),
            $this->float('tarifParDemiJournee'),
            $this->float('tarifParHeure'),
            $this->boolNull('surDevis'),
            $this->text('youtubeUrl', 255),
        ];
    }

    public function collections(): array
    {
        return [
            new CollectionSchema('acces', self::MAX_ACCES, ServiceAcces::class, 'addAcces', 'acces', [
                new ColumnDefinition('type', ColumnKind::Enum, 'type', enumClass: TypeAccesService::class, required: true, nullable: false),
                new ColumnDefinition('nom', ColumnKind::Text, 'nom', required: true, nullable: false),
                new ColumnDefinition('distance_kilometres', ColumnKind::Decimal, 'distanceKilometres', help: 'décimal, point comme séparateur'),
                new ColumnDefinition('duree_minutes', ColumnKind::Int, 'dureeMinutes'),
                new ColumnDefinition('mode_transport', ColumnKind::Text, 'modeTransport'),
            ]),
        ];
    }

    public function lovChoices(): array
    {
        return [
            'TYPE_PRESTATAIRE' => ServiceLovCatalog::prestations(),
            'SOUS_PRESTATION' => ServiceLovCatalog::sousPrestations(),
        ];
    }

    public function createAggregate(): object
    {
        return new ServiceEvenementiel();
    }

    public function findAggregateByFiche(Fiche $fiche): ?object
    {
        return $this->services->findOneByFiche($fiche);
    }

    public function ficheOf(object $aggregate): Fiche
    {
        if (!$aggregate instanceof ServiceEvenementiel) {
            throw new \LogicException('Agrégat inattendu pour le type service événementiel.');
        }

        return $aggregate->fiche();
    }
}
