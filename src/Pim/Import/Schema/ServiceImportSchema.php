<?php

declare(strict_types=1);

namespace App\Pim\Import\Schema;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\ModeInterventionService;
use App\Pim\Enum\TypeFiche;
use App\Pim\Lov\ServiceLovCatalog;
use App\Pim\Repository\ServiceEvenementielRepository;

final class ServiceImportSchema extends AbstractFicheImportSchema
{
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
            $this->list('paysMobiles'),
            $this->list('regionsMobiles'),
            $this->list('departementsMobiles'),
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
        return [];
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
