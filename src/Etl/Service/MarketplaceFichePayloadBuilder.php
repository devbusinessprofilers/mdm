<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Dam\Entity\MediaAsset;
use App\Dam\Repository\MediaAssetRepository;
use App\Enrichment\Enum\TranslationStatus;
use App\Enrichment\Repository\FicheTranslationRepository;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\NatureRessource;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Repository\LieuRepository;
use App\Pim\Repository\RestaurantRepository;
use App\Pim\Repository\ServiceEvenementielRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Construit le snapshot complet d'une fiche pour l'API marketplace : données
 * françaises (colonnes *_fr côté marketplace), traductions finalisées par
 * locale (tables i18n) et photos (chemins relatifs des rendus DAM).
 *
 * Les clés de `data` et `translations` sont les noms du modèle marketplace :
 * le mapping champ PIM → champ marketplace vit ici, la marketplace reste
 * passive. Un champ-locale absent de `translations` signifie « pas de
 * traduction disponible » : la marketplace supprime la ligne i18n et le
 * fallback français de Gedmo s'applique.
 */
final readonly class MarketplaceFichePayloadBuilder
{
    /**
     * Chemins de traduction PIM → champs plats marketplace. Les libellés par
     * élément (salles, légendes…) ne sont pas repris en v1.
     */
    private const TRANSLATION_FIELDS = [
        'nom' => 'nom',
        'lieu.descGenerale' => 'description',
        'restaurant.descriptionGenerale' => 'description',
        'activite.descriptionGenerale' => 'description',
        'service.descriptionGenerale' => 'description',
        'lieu.descGeneralePointInteret' => 'pointsInteret',
        'lieu.chambreDescGenerale' => 'chambres',
        'lieu.salleReunionDescSalleSeminaire' => 'salles',
        'lieu.rseDescGenerale' => 'rse',
        'lieu.pmrDetails' => 'pmrDetails',
        'activite.comprendPrestation' => 'comprendPrestation',
    ];

    public function __construct(
        private LieuRepository $lieux,
        private ActiviteRepository $activites,
        private RestaurantRepository $restaurants,
        private ServiceEvenementielRepository $services,
        private FicheTranslationRepository $translations,
        private MediaAssetRepository $assets,
        #[Autowire(env: 'S3_PREFIX')]
        private string $storagePrefix,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(Fiche $fiche): array
    {
        return [
            'pimId' => $fiche->idString(),
            'code' => $fiche->code(),
            'type' => $fiche->type()->value,
            'version' => $fiche->version(),
            'publishedAt' => $fiche->publishedAt()?->format(\DateTimeInterface::ATOM),
            'businessPremium' => $fiche->businessPremium(),
            'data' => $this->data($fiche),
            'translations' => $this->translationsFor($fiche),
            'photos' => $this->photos($fiche),
        ];
    }

    /** @return array<string, mixed> */
    private function data(Fiche $fiche): array
    {
        $data = [
            'nom' => $fiche->label(),
            'adresse' => $this->adresse($fiche),
        ];
        $detail = match ($fiche->type()->value) {
            'lieu' => $this->lieux->find($fiche->id()),
            'restaurant' => $this->restaurants->find($fiche->id()),
            'activite' => $this->activites->find($fiche->id()),
            'service_evenementiel' => $this->services->find($fiche->id()),
            default => null,
        };

        return $data + match (true) {
            $detail instanceof Lieu => $this->lieu($detail),
            $detail instanceof Restaurant => $this->restaurant($detail),
            $detail instanceof Activite => $this->activite($detail),
            $detail instanceof ServiceEvenementiel => $this->service($detail),
            default => [],
        };
    }

    /** @return array<string, mixed>|null */
    private function adresse(Fiche $fiche): ?array
    {
        $localisation = $fiche->localisation();
        if (null === $localisation) {
            return null;
        }

        return [
            'rue' => $localisation->ruePostale(),
            'codePostal' => $localisation->codePostal(),
            'ville' => $localisation->ville(),
            'arrondissement' => $localisation->arrondissement(),
            'departement' => $localisation->departement(),
            'region' => $localisation->region(),
            'pays' => $localisation->pays(),
            'latitude' => $localisation->latitude(),
            'longitude' => $localisation->longitude(),
        ];
    }

    /** @return array<string, mixed> */
    private function lieu(Lieu $lieu): array
    {
        return [
            'description' => $lieu->descGenerale(),
            'pointsInteret' => $lieu->descGeneralePointInteret(),
            'atouts' => array_values(array_filter([
                $lieu->atout1(),
                $lieu->atout2(),
                $lieu->atout3(),
                $lieu->atout4(),
                $lieu->atout5(),
            ], static fn (?string $v): bool => null !== $v && '' !== trim($v))),
            'gamme' => $lieu->generaleGammeLibelle(),
            'siteWeb' => $lieu->generaleWebsiteUrl(),
            'youtube' => $lieu->generaleYoutube(),
            'privatisable' => $lieu->dispoLieuPrivatisable(),
            'pmr' => [
                'acces' => $lieu->pmrAcces(),
                'details' => $lieu->pmrDetails(),
            ],
            'chambres' => !$lieu->chambreHebergement() ? null : [
                'total' => $lieu->chambreNbTotal(),
                'single' => $lieu->chambreNbTotalSingle(),
                'twin' => $lieu->chambreNbTotalTwin(),
                'double' => $lieu->chambreDouble(),
                'capaciteTotale' => $lieu->chambreCapaciteTotale(),
                'description' => $lieu->chambreDescGenerale(),
            ],
            'sallesReunion' => !$lieu->salleReunionExist() ? null : [
                'total' => $lieu->salleReunionNbTotal(),
                'capaciteMaxCocktail' => $lieu->salleReunionCapaciteMaxCocktail(),
                'capaciteMaxTheatre' => $lieu->salleReunionCapaciteMaxTheatre(),
                'capaciteMinTheatre' => $lieu->salleReunionCapaciteMinTheatre(),
                'surfaceMin' => $lieu->salleReunionSurfaceMinReunion(),
                'surfaceMax' => $lieu->salleReunionSurfaceMaxReunion(),
                'description' => $lieu->salleReunionDescSalleSeminaire(),
            ],
            'restaurantInterne' => [
                'total' => $lieu->restaurantTotal(),
                'salles' => $lieu->restaurantSalleRestauration(),
                'capaciteDebout' => $lieu->restaurantCapaciteDebout(),
                'capaciteAssis' => $lieu->restaurantCapaciteAssis(),
                'privatisable' => $lieu->restaurantPrivatisable(),
            ],
            'rse' => $lieu->rseDescGenerale(),
            'salles' => array_map(static fn ($salle): array => [
                'nom' => $salle->nom(),
                'superficie' => $salle->superficie(),
                'capaciteReunion' => $salle->capaciteReunion(),
                'capaciteU' => $salle->capaciteU(),
                'capaciteClasse' => $salle->capaciteClasse(),
                'capaciteTheatre' => $salle->capaciteTheatre(),
                'capaciteCabaret' => $salle->capaciteCabaret(),
                'capaciteBanquet' => $salle->capaciteBanquet(),
                'capaciteCocktail' => $salle->capaciteCocktail(),
                'capaciteAuditorium' => $salle->capaciteAuditorium(),
                'lumiereJour' => $salle->lumiereJour(),
                'accesPmr' => $salle->accesPmr(),
                'position' => $salle->position(),
            ], $lieu->salles()->toArray()),
        ];
    }

    /** @return array<string, mixed> */
    private function restaurant(Restaurant $restaurant): array
    {
        return [
            'description' => $restaurant->descriptionGenerale(),
            'atouts' => $restaurant->atouts(),
            'siteWeb' => $restaurant->siteOfficiel(),
            'youtube' => $restaurant->youtubeUrl(),
            'privatisation' => [
                'totale' => $restaurant->privatisationTotale(),
                'partielle' => $restaurant->privatisationPartielle(),
            ],
            'horaires' => [
                'ouverture' => $restaurant->heureOuverture()?->format('H:i'),
                'fermeture' => $restaurant->heureFermeture()?->format('H:i'),
            ],
            'pmr' => [
                'acces' => $restaurant->accesPmr(),
                'toilettes' => $restaurant->toilettesPmr(),
            ],
            'capacites' => [
                'assiseMax' => $restaurant->capaciteAssiseMax(),
                'espacePrivatisable' => $restaurant->capaciteEspacePrivatisable(),
                'banquet' => $restaurant->capaciteBanquet(),
                'cocktail' => $restaurant->capaciteCocktail(),
            ],
            'typesRestaurant' => $restaurant->typesRestaurant(),
            'typesCuisine' => $restaurant->typesCuisine(),
            'specificitesAlimentaires' => $restaurant->specificitesAlimentaires(),
            'typesEvenement' => $restaurant->typesEvenement(),
            'joursOuverture' => $restaurant->joursOuverture(),
            'services' => $restaurant->services(),
            'equipements' => $restaurant->equipements(),
            'engagementsRse' => $restaurant->engagementsRse(),
            'salles' => array_map(static fn ($salle): array => [
                'nom' => $salle->nom(),
                'superficie' => $salle->superficie(),
                'capaciteTheatre' => $salle->capaciteTheatre(),
                'capaciteBanquet' => $salle->capaciteBanquet(),
                'capaciteCocktail' => $salle->capaciteCocktail(),
                'accesPmr' => $salle->accesPmr(),
                'position' => $salle->position(),
            ], $restaurant->salles()->toArray()),
        ];
    }

    /** @return array<string, mixed> */
    private function activite(Activite $activite): array
    {
        return [
            'description' => $activite->descriptionGenerale(),
            'comprendPrestation' => $activite->comprendPrestation(),
            'atouts' => $activite->plus(),
            'participants' => [
                'min' => $activite->participantsMin(),
                'max' => $activite->participantsMax(),
            ],
            'duree' => [
                'minMinutes' => $activite->dureeMinMinutes(),
                'maxMinutes' => $activite->dureeMaxMinutes(),
            ],
            'tarifParPersonne' => $activite->tarifParPersonne(),
            'youtube' => $activite->youtubeUrl(),
            'modeIntervention' => $activite->modeIntervention()?->value,
            'couverture' => [
                'touteFrance' => $activite->touteFrance(),
                'pays' => $activite->paysMobiles(),
                'regions' => $activite->regionsMobiles(),
                'departements' => $activite->departementsMobiles(),
            ],
            'types' => $activite->types(),
            'thematiques' => $activite->thematiques(),
            'sousThematiques' => $activite->sousThematiques(),
            'langues' => $activite->langues(),
            'objectifs' => $activite->objectifs(),
            'engagementsRse' => $activite->engagementsRse(),
            'offres' => array_values(array_filter(array_map(static fn ($offre): ?array => null === $offre->nom() ? null : [
                'type' => $offre->type()->value,
                'nom' => $offre->nom(),
            ], $activite->offres()->toArray()))),
        ];
    }

    /** @return array<string, mixed> */
    private function service(ServiceEvenementiel $service): array
    {
        return [
            'description' => $service->descriptionGenerale(),
            'prestations' => $service->prestations(),
            'youtube' => $service->youtubeUrl(),
            'modeIntervention' => $service->modeIntervention()?->value,
            'couverture' => [
                'pays' => $service->paysMobiles(),
                'regions' => $service->regionsMobiles(),
                'departements' => $service->departementsMobiles(),
            ],
            'tarifs' => [
                'parPrestation' => $service->tarifParPrestation(),
                'parPersonne' => $service->tarifParPersonne(),
                'parJour' => $service->tarifParJour(),
                'parDemiJournee' => $service->tarifParDemiJournee(),
                'parHeure' => $service->tarifParHeure(),
                'surDevis' => $service->surDevis(),
            ],
            'demarcheRse' => $service->demarcheRse(),
            'prestataireEsat' => $service->prestataireEsat(),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function translationsFor(Fiche $fiche): array
    {
        $atoutPaths = [
            'lieu.atout1' => 0, 'lieu.atout2' => 1, 'lieu.atout3' => 2,
            'lieu.atout4' => 3, 'lieu.atout5' => 4,
        ];
        $result = [];
        foreach ($this->translations->forFiche($fiche) as $translation) {
            if (
                TranslationStatus::Available !== $translation->status()
                || null === $translation->translatedText()
            ) {
                continue;
            }
            $locale = $translation->locale()->value;
            $path = $translation->fieldPath();
            $field = self::TRANSLATION_FIELDS[$path] ?? null;
            if (null !== $field) {
                $result[$locale][$field] = $translation->translatedText();
                continue;
            }
            $index = $atoutPaths[$path]
                ?? self::listIndex($path, 'restaurant.atouts')
                ?? self::listIndex($path, 'activite.plus');
            if (null !== $index) {
                $result[$locale]['atouts'][$index] = $translation->translatedText();
            }
        }
        foreach ($result as $locale => $fields) {
            $atouts = $fields['atouts'] ?? null;
            if (\is_array($atouts)) {
                ksort($atouts);
                $result[$locale]['atouts'] = array_values($atouts);
            }
        }
        ksort($result);

        return $result;
    }

    private static function listIndex(string $path, string $prefix): ?int
    {
        if (1 === preg_match('/^'.preg_quote($prefix, '/').'\[(\d+)\]$/', $path, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function photos(Fiche $fiche): array
    {
        $resources = array_values(array_filter(
            $fiche->resources()->toArray(),
            static fn (RessourceLieu $r): bool => NatureRessource::Photo === $r->nature(),
        ));
        usort(
            $resources,
            static fn (RessourceLieu $a, RessourceLieu $b): int => [$a->position(), $a->id()] <=> [$b->position(), $b->id()],
        );
        $assets = [];
        foreach ($this->assets->findByStringIds(array_map(
            static fn (RessourceLieu $r): string => $r->damAssetId(),
            $resources,
        )) as $asset) {
            $assets[$asset->id()] = $asset;
        }
        $photos = [];
        foreach ($resources as $resource) {
            $asset = $assets[$resource->damAssetId()] ?? null;
            $location = null === $asset ? null : $this->location($asset);
            if (null === $location) {
                continue;
            }
            $photos[] = [
                'location' => $location,
                'legende' => $resource->legende(),
                'principale' => [] === $photos,
                'position' => $resource->position(),
            ];
        }

        return $photos;
    }

    /**
     * Chemin relatif de la photo, commun à toutes les variantes : la clé DAM
     * `{prefix}/photos/{variante}/{chemin}` privée de sa base. La marketplace
     * le stocke en bp_photo.location et compose ses URLs avec ses variables
     * d'environnement `{base}/{variante}/`.
     */
    private function location(MediaAsset $asset): ?string
    {
        $rendition = $asset->rendition('large');
        if (null === $rendition) {
            return null;
        }
        $prefix = trim($this->storagePrefix, '/');
        $base = ('' === $prefix ? '' : $prefix.'/').'photos/large/';
        $key = $rendition->storageKey();
        if (!str_starts_with($key, $base)) {
            return null;
        }

        return substr($key, strlen($base));
    }
}
