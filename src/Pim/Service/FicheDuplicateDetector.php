<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Localisation;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\FicheCreation;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\LieuRepository;
use App\Shared\Search\SearchQuery;
use App\Shared\Service\SearchEngineInterface;

/**
 * Repère les fiches existantes similaires à une création en cours, à partir de
 * la saisie utilisateur et des données renvoyées par l'annuaire des
 * entreprises. Signal, pas verdict : la création reste possible après
 * confirmation explicite.
 */
final readonly class FicheDuplicateDetector
{
    private const MAX_CANDIDATES = 5;

    public function __construct(
        private FicheRepository $fiches,
        private LieuRepository $lieux,
        private SearchEngineInterface $searchEngine,
        private FicheRouteResolver $routes,
    ) {
    }

    /** @return list<FicheDuplicateCandidate> */
    public function detect(FicheCreation $data, ?EntrepriseInfo $entreprise): array
    {
        /** @var array<string, list<string>> $reasonsById */
        $reasonsById = [];
        foreach ($this->matchByLabel($data, $entreprise) as $ficheId) {
            $reasonsById[$ficheId][] = 'nom';
        }
        foreach ($this->fiches->findIdsByAddressFingerprints($this->fingerprints($data, $entreprise)) as $ficheId) {
            $reasonsById[$ficheId][] = 'adresse';
        }
        if (null !== $entreprise?->siret) {
            foreach ($this->lieux->findFicheIdsBySiret($entreprise->siret) as $ficheId) {
                $reasonsById[$ficheId][] = 'siret';
            }
        }
        if ([] === $reasonsById) {
            return [];
        }

        $candidates = [];
        $ids = array_slice(array_keys($reasonsById), 0, self::MAX_CANDIDATES);
        foreach ($this->fiches->findGlobalSearchItemsByIds($ids) as $item) {
            $candidates[] = new FicheDuplicateCandidate(
                ficheId: $item->id,
                type: $item->type,
                code: $item->code,
                label: $item->label,
                ville: $item->ville,
                status: $item->status,
                reasons: array_values(array_unique($reasonsById[$item->id])),
                url: TypeFiche::Traiteur === $item->type ? null : $this->routes->showUrl($item->type, $item->id),
            );
        }

        return $candidates;
    }

    /** @return list<string> */
    private function matchByLabel(FicheCreation $data, ?EntrepriseInfo $entreprise): array
    {
        $ids = [];
        $labels = array_filter([trim((string) $data->label), trim((string) $entreprise?->denomination)]);
        foreach (array_unique($labels) as $label) {
            // Correspondance exacte directe : couvre les fiches pas encore
            // présentes dans l'index fulltext (indexation asynchrone).
            $ids = [...$ids, ...$this->fiches->findIdsByLabelInsensitive($label)];
        }
        $query = trim((string) $data->label);
        if ('' !== $query) {
            foreach ($this->searchEngine->search(new SearchQuery($query, [], self::MAX_CANDIDATES))->results as $result) {
                $ids[] = $result->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<string> empreintes binaires d'adresse candidates */
    private function fingerprints(FicheCreation $data, ?EntrepriseInfo $entreprise): array
    {
        $fingerprints = [];
        $saisie = $data->localisation?->addressFingerprint();
        if (null !== $saisie) {
            $fingerprints[] = $saisie;
        }
        if (null !== $entreprise) {
            $api = new Localisation();
            $api->changeCountryCode('FR');
            $api->changeRuePostale($entreprise->rue);
            $api->changeCodePostal($entreprise->codePostal);
            $api->changeVille($entreprise->ville);
            if (null !== $api->addressFingerprint()) {
                $fingerprints[] = $api->addressFingerprint();
            }
        }

        return array_values(array_unique($fingerprints));
    }
}
