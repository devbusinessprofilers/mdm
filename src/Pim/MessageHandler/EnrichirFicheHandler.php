<?php

declare(strict_types=1);

namespace App\Pim\MessageHandler;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\ResultatSourceEnrichissement;
use App\Pim\Enum\SuggestionSource;
use App\Pim\Enum\TypeFiche;
use App\Pim\Message\EnrichirFiche;
use App\Pim\Message\VerifierAdresseFiche;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Repository\FicheEnrichmentRunRepository;
use App\Pim\Repository\FicheEnrichmentScanRepository;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\LieuRepository;
use App\Pim\Repository\RestaurantRepository;
use App\Pim\Repository\ServiceEvenementielRepository;
use App\Pim\Service\AtoutsIaVerifier;
use App\Pim\Service\ChaineHoteliereVerifier;
use App\Pim\Service\ClassementAtoutFranceVerifier;
use App\Pim\Service\DataTourisme\DataTourismeFluxReader;
use App\Pim\Service\DataTourisme\DataTourismeIndex;
use App\Pim\Service\DataTourismeVerifier;
use App\Pim\Service\DescriptionIaVerifier;
use App\Pim\Service\EnrichissementIndisponibleException;
use App\Pim\Service\FicheSuggestionEnregistreur;
use App\Pim\Service\GeoapifyClient;
use App\Pim\Service\LieuAttributsVerifier;
use App\Pim\Service\RestaurantAttributsVerifier;
use App\Pim\Service\StatutEtablissementVerifier;
use App\Pim\Service\SuggestionProposee;
use App\Pim\Service\Wikidata\ChaineDictionnaire;
use App\Pim\Service\Wikidata\WikidataChaineClient;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Service\ParametreProviderInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

/**
 * Enrichit une fiche à la demande (bouton « Enrichir ce qui manque ») : mêmes
 * vérificateurs et mêmes gates que les scans batch, mais sur cette seule fiche
 * et sans garde de fraîcheur — le clic vaut demande de re-scan. Chaque source
 * est isolée : une API en panne (EnrichissementIndisponibleException) n'empêche
 * pas les autres et ne marque pas la fiche comme scannée. Les suggestions
 * atterrissent dans le bloc « Suggestions en attente » de la fiche et l'onglet
 * Conflits de /qualite ; l'adresse repart par le flux BAN habituel.
 */
#[WithMonologChannel('enrichment')]
#[AsMessageHandler]
final readonly class EnrichirFicheHandler
{
    public function __construct(
        private FicheRepository $fiches,
        private LieuRepository $lieux,
        private RestaurantRepository $restaurants,
        private ActiviteRepository $activites,
        private ServiceEvenementielRepository $services,
        private StatutEtablissementVerifier $statutsEtablissement,
        private RestaurantAttributsVerifier $attributsRestaurant,
        private LieuAttributsVerifier $attributsLieu,
        private DataTourismeVerifier $dataTourisme,
        private ChaineHoteliereVerifier $chainesHotelieres,
        private ClassementAtoutFranceVerifier $classementsAtoutFrance,
        private DescriptionIaVerifier $descriptionsIa,
        private AtoutsIaVerifier $atoutsIa,
        private DataTourismeFluxReader $flux,
        private WikidataChaineClient $wikidata,
        private GeoapifyClient $geoapify,
        private FicheSuggestionEnregistreur $enregistreur,
        private FicheEnrichmentScanRepository $scans,
        private FicheEnrichmentRunRepository $runs,
        private ParametreProviderInterface $parametres,
        private OutboxPublisherInterface $outbox,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(EnrichirFiche $message): void
    {
        $fiche = $this->fiches->parId($message->ficheId);
        if (!$fiche instanceof Fiche) {
            return;
        }
        // L'adresse suit son flux dédié (BAN France / Geoapify étranger) : le
        // handler d'adresse porte sa propre garde d'empreinte, le re-dispatch
        // est inoffensif si elle est déjà vérifiée.
        $this->outbox->enqueue(new VerifierAdresseFiche($fiche->idString()));

        $type = $fiche->type();
        $lieu = TypeFiche::Lieu === $type ? $this->lieux->findOneByFiche($fiche) : null;
        $restaurant = TypeFiche::Restaurant === $type ? $this->restaurants->findOneByFiche($fiche) : null;
        $activite = TypeFiche::Activite === $type ? $this->activites->findOneByFiche($fiche) : null;
        $service = TypeFiche::ServiceEvenementiel === $type ? $this->services->findOneByFiche($fiche) : null;

        // Résultat par source, journalisé sur la demande (visible dans /outils) :
        // « inactif » (gate off), « non_configuree » (clé API ou flux manquant),
        // « sans_adresse » (code postal requis absent), « indisponible » (panne
        // API) ou nombre de suggestions créées/rafraîchies.
        $resultat = ['adresse' => ResultatSourceEnrichissement::VerificationEnfilee->value];
        if (null !== $lieu) {
            // Le rapprochement sans SIRET (backfill) est inclus d'office : le
            // batch le réserve à une option, mais le clic demande tout.
            $resultat['sirene'] = $this->parametres->bool('sirene.verif_statut_actif')
                ? $this->executer($fiche, SuggestionSource::Sirene, fn (): array => $this->statutsEtablissement->analyser($lieu))
                : ResultatSourceEnrichissement::Inactif->value;
        }
        if (null !== $restaurant || null !== $lieu) {
            $resultat['geoapify'] = match (true) {
                !$this->parametres->bool('geoapify.enrichissement_places') => ResultatSourceEnrichissement::Inactif->value,
                !$this->geoapify->isConfigured() => ResultatSourceEnrichissement::NonConfiguree->value,
                null !== $restaurant => $this->executer($fiche, SuggestionSource::Geoapify, fn (): array => $this->attributsRestaurant->analyser($restaurant)),
                default => $this->executer($fiche, SuggestionSource::Geoapify, fn (): array => $this->attributsLieu->analyser($lieu)),
            };
        }
        $codePostal = trim((string) $fiche->localisation()?->codePostal());
        if (null !== $lieu || null !== $activite) {
            $resultat['datatourisme'] = match (true) {
                !$this->parametres->bool('datatourisme.import_actif') => ResultatSourceEnrichissement::Inactif->value,
                !$this->flux->isConfigured() => ResultatSourceEnrichissement::NonConfiguree->value,
                '' === $codePostal => ResultatSourceEnrichissement::SansAdresse->value,
                default => $this->executer($fiche, SuggestionSource::DataTourisme, function () use ($lieu, $activite, $codePostal): array {
                    $index = DataTourismeIndex::depuis($this->flux->lire(), [$codePostal]);

                    return null !== $lieu ? $this->dataTourisme->analyserLieu($lieu, $index) : $this->dataTourisme->analyserActivite($activite, $index);
                }),
            };
        }
        if (null !== $lieu) {
            $resultat['wikidata'] = $this->parametres->bool('wikidata.detection_chaine')
                ? $this->executer($fiche, SuggestionSource::Wikidata, fn (): array => $this->chainesHotelieres->analyser($lieu, ChaineDictionnaire::depuis($this->wikidata->chaines())))
                : ResultatSourceEnrichissement::Inactif->value;
            // Classement officiel Atout France (référentiel local, jamais
            // « indisponible ») : étoiles → typologie, nombre de chambres.
            $resultat['atout_france'] = $this->parametres->bool('atout_france.classement_actif')
                ? $this->executer($fiche, SuggestionSource::AtoutFrance, fn (): array => $this->classementsAtoutFrance->analyser($lieu))
                : ResultatSourceEnrichissement::Inactif->value;
        }
        [$champIa, $description] = match (true) {
            null !== $lieu => ['lieu_desc_generale', $lieu->descGenerale()],
            null !== $restaurant => ['restaurant_desc_generale', $restaurant->descriptionGenerale()],
            null !== $activite => ['activite_desc_generale', $activite->descriptionGenerale()],
            null !== $service => ['service_desc_generale', $service->descriptionGenerale()],
            default => [null, null],
        };
        // Atouts par gamme (le Service n'en a pas) : générés depuis la
        // description ACTUELLE de la fiche — une description encore en
        // suggestion non arbitrée ne compte pas, les atouts suivront au
        // prochain scan une fois la description acceptée.
        [$champAtouts, $atoutsActuels, $atoutsMax, $atoutsLongueurMax] = match (true) {
            null !== $lieu => ['lieu_atouts', array_values(array_filter(
                [$lieu->atout1(), $lieu->atout2(), $lieu->atout3(), $lieu->atout4(), $lieu->atout5()],
                static fn (?string $atout): bool => null !== $atout && '' !== trim($atout),
            )), 5, Lieu::ATOUT_MAX_LENGTH],
            null !== $restaurant => ['restaurant_atouts', $restaurant->atouts(), 5, 80],
            null !== $activite => ['activite_plus', $activite->plus(), 4, 80],
            default => [null, [], 0, 0],
        };
        if (null !== $champIa) {
            $resultat['ia'] = $this->parametres->bool('openai.actif')
                ? $this->executer($fiche, SuggestionSource::Ia, fn (): array => [
                    ...$this->descriptionsIa->analyser($fiche, $description, $champIa),
                    ...(null === $champAtouts ? [] : $this->atoutsIa->analyser($fiche, $description, $champAtouts, $atoutsActuels, $atoutsMax, $atoutsLongueurMax)),
                ])
                : ResultatSourceEnrichissement::Inactif->value;
        }

        $this->terminerRun($message, $fiche, array_map(
            static fn (int|string|null $valeur): int|string => $valeur ?? ResultatSourceEnrichissement::Indisponible->value,
            $resultat,
        ));
        $this->logger->info('Fiche enrichie à la demande.', ['fiche' => $fiche->idString(), 'resultat' => $resultat]);
    }

    /**
     * Complète la trace de la demande (journal /outils). Le run est créé au
     * clic ; repli sur la plus ancienne demande en attente (ou une trace créée
     * ici) pour les messages sans id de run.
     *
     * @param array<string, int|string> $resultat
     */
    private function terminerRun(EnrichirFiche $message, Fiche $fiche, array $resultat): void
    {
        $run = null !== $message->runId ? $this->runs->find(Ulid::fromString($message->runId)) : null;
        $run ??= $this->runs->plusAncienneEnAttente($fiche) ?? $this->runs->demarrer($fiche);
        $run->terminer($resultat);
    }

    /**
     * Passe une source sur la fiche : suggestions enregistrées puis trace de
     * scan (l'id de fiche est la clé de la trace ; les gammes partagent l'ULID
     * de leur fiche). Une API indisponible laisse la fiche « à re-scanner ».
     *
     * @param callable(): list<SuggestionProposee> $analyser
     *
     * @return int|null nombre de suggestions créées, null si la source était indisponible
     */
    private function executer(Fiche $fiche, SuggestionSource $source, callable $analyser): ?int
    {
        try {
            $creees = $this->enregistreur->enregistrer($fiche, $source, $analyser());
        } catch (EnrichissementIndisponibleException $exception) {
            $this->logger->warning('Source d\'enrichissement indisponible.', [
                'fiche' => $fiche->idString(),
                'source' => $source->value,
                'erreur' => $exception->getMessage(),
            ]);

            return null;
        }
        $this->scans->marquer([$fiche->idString()], $source, new \DateTimeImmutable());

        return $creees;
    }
}
