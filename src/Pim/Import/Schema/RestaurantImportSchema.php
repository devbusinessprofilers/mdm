<?php

declare(strict_types=1);

namespace App\Pim\Import\Schema;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantAcces;
use App\Pim\Entity\Restaurant\RestaurantPeriodeFermeture;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use App\Pim\Enum\TypeAccesRestaurant;
use App\Pim\Enum\TypeFiche;
use App\Pim\Lov\RestaurantLovCatalog;
use App\Pim\Repository\RestaurantRepository;

final class RestaurantImportSchema extends AbstractFicheImportSchema
{
    public const MAX_SALLES = 20;
    public const MAX_PERIODES_FERMETURE = 10;
    public const MAX_ACCES = 10;

    private const LOV_ATTRIBUTES = [
        'TYPE_RESTAURANT', 'TYPE_CUISINE', 'SPECIFICITE_ALIMENTAIRE', 'TYPE_EVENEMENT',
        'DISPO_JOUR_OUVERTURE', 'SERVICE_RESTAURANT', 'EQUIPEMENT_RESTAURANT', 'ENGAGEMENT_RSE_RESTAURANT',
    ];

    public function __construct(private readonly RestaurantRepository $restaurants)
    {
    }

    public function type(): TypeFiche
    {
        return TypeFiche::Restaurant;
    }

    public function ficheColumns(): array
    {
        return [
            ...$this->commonColumns(),
            $this->lovMulti('typesRestaurant', 'TYPE_RESTAURANT'),
            $this->lovMulti('typesCuisine', 'TYPE_CUISINE'),
            $this->lovMulti('specificitesAlimentaires', 'SPECIFICITE_ALIMENTAIRE'),
            $this->lovMulti('typesEvenement', 'TYPE_EVENEMENT'),
            $this->lovMulti('joursOuverture', 'DISPO_JOUR_OUVERTURE'),
            $this->lovMulti('services', 'SERVICE_RESTAURANT'),
            $this->lovMulti('equipements', 'EQUIPEMENT_RESTAURANT'),
            $this->lovMulti('engagementsRse', 'ENGAGEMENT_RSE_RESTAURANT'),
            $this->text('siteOfficiel', 100),
            $this->boolNull('privatisationTotale'),
            $this->boolNull('privatisationPartielle'),
            ...$this->horairesJours(RestaurantLovCatalog::values('DISPO_JOUR_OUVERTURE')),
            $this->boolNull('accesPmr'),
            $this->boolNull('toilettesPmr'),
            $this->text('descriptionGenerale'),
            $this->list('atouts'),
            $this->int('capaciteAssiseMax'),
            $this->int('capaciteEspacePrivatisable'),
            $this->int('capaciteBanquet'),
            $this->int('capaciteCocktail'),
            $this->text('youtubeUrl', 255),
        ];
    }

    public function collections(): array
    {
        $salleColumns = [
            new ColumnDefinition('nom', ColumnKind::Text, 'nom', required: true, nullable: false),
            new ColumnDefinition('superficie', ColumnKind::Int, 'superficie', help: 'en m²'),
            new ColumnDefinition('capacite_reunion', ColumnKind::Int, 'capaciteReunion'),
            new ColumnDefinition('capacite_u', ColumnKind::Int, 'capaciteU'),
            new ColumnDefinition('capacite_classe', ColumnKind::Int, 'capaciteClasse'),
            new ColumnDefinition('capacite_theatre', ColumnKind::Int, 'capaciteTheatre'),
            new ColumnDefinition('capacite_cabaret', ColumnKind::Int, 'capaciteCabaret'),
            new ColumnDefinition('capacite_banquet', ColumnKind::Int, 'capaciteBanquet'),
            new ColumnDefinition('capacite_cocktail', ColumnKind::Int, 'capaciteCocktail'),
            new ColumnDefinition('capacite_auditorium', ColumnKind::Int, 'capaciteAuditorium'),
            new ColumnDefinition('lumiere_jour', ColumnKind::Bool, 'lumiereJour', nullable: false),
            new ColumnDefinition('acces_pmr', ColumnKind::Bool, 'accesPmr', nullable: false),
            new ColumnDefinition('espace_dansant', ColumnKind::Bool, 'espaceDansant', nullable: false),
            new ColumnDefinition('climatisee', ColumnKind::Bool, 'climatisee', nullable: false),
        ];

        return [
            new CollectionSchema('salle', self::MAX_SALLES, RestaurantSalle::class, 'addSalle', 'salles', $salleColumns),
            new CollectionSchema('periode_fermeture', self::MAX_PERIODES_FERMETURE, RestaurantPeriodeFermeture::class, 'addPeriodeFermeture', 'periodesFermeture', [
                new ColumnDefinition('nom', ColumnKind::Text, 'nom', required: true, nullable: false),
                new ColumnDefinition('date_debut', ColumnKind::Date, 'dateDebut'),
                new ColumnDefinition('date_fin', ColumnKind::Date, 'dateFin'),
            ]),
            new CollectionSchema('acces', self::MAX_ACCES, RestaurantAcces::class, 'addAcces', 'acces', [
                new ColumnDefinition('type', ColumnKind::Enum, 'type', enumClass: TypeAccesRestaurant::class, required: true, nullable: false),
                new ColumnDefinition('nom', ColumnKind::Text, 'nom', required: true, nullable: false),
            ]),
        ];
    }

    public function lovChoices(): array
    {
        $choices = [];
        foreach (self::LOV_ATTRIBUTES as $attribute) {
            $choices[$attribute] = RestaurantLovCatalog::values($attribute);
        }

        return $choices;
    }

    public function createAggregate(): object
    {
        return new Restaurant();
    }

    public function findAggregateByFiche(Fiche $fiche): ?object
    {
        return $this->restaurants->findOneByFiche($fiche);
    }

    public function ficheOf(object $aggregate): Fiche
    {
        if (!$aggregate instanceof Restaurant) {
            throw new \LogicException('Agrégat inattendu pour le type restaurant.');
        }

        return $aggregate->fiche();
    }
}
