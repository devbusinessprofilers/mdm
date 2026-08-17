<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Service\PublicMediaUrlGenerator;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\ReferentielFiltres;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\ReadModel\ReferentielLigne;
use App\Pim\ReadModel\ReferentielVue;
use App\Pim\Repository\ReferentielRepository;
use Symfony\Component\Uid\Ulid;

/**
 * Assemble la vue de la liste du référentiel : lignes hydratées (auteur,
 * vignette, marqueur IA), comptes de facettes et pagination par curseur.
 * Les requêtes vivent dans ReferentielRepository.
 */
final readonly class ReferentielListeProvider
{
    public function __construct(
        private ReferentielRepository $repository,
        private PublicMediaUrlGenerator $publicUrl,
    ) {
    }

    public function vue(ReferentielFiltres $filtres, ?FicheCursor $cursor = null, int $limit = 14): ReferentielVue
    {
        $limit = max(1, min(100, $limit));
        [$lignes, $nextCursor] = $this->page($filtres, $cursor, $limit);

        return new ReferentielVue(
            lignes: $lignes,
            nextCursor: $nextCursor,
            total: $this->repository->count($filtres),
            totalReferentiel: $this->repository->totalReferentiel(),
            comptes: $this->repository->comptes($filtres),
            paysChoices: $this->repository->paysChoices(),
            valeursChoices: $this->repository->valeursChoices($filtres),
            contributeursChoices: $this->repository->contributeursChoices($filtres),
        );
    }

    /** @return list<string> Identifiants (ULID texte) de toutes les fiches du filtre, pour les actions groupées. */
    public function idsPourFiltre(ReferentielFiltres $filtres, int $plafond): array
    {
        return $this->repository->idsPourFiltre($filtres, $plafond);
    }

    /** @return array{precedente: ?string, suivante: ?string} */
    public function voisines(ReferentielFiltres $filtres, string $ficheId): array
    {
        return $this->repository->voisines($filtres, $ficheId);
    }

    /** @return list<ReferentielLigne> Lignes du filtre pour l'export, plafonnées. */
    public function exportLignes(ReferentielFiltres $filtres, int $plafond): array
    {
        $lignes = [];
        $cursor = null;
        while (count($lignes) < $plafond) {
            [$page, $next] = $this->page($filtres, $cursor, min(100, $plafond - count($lignes)));
            $lignes = [...$lignes, ...$page];
            if (null === $next) {
                break;
            }
            $cursor = FicheCursor::decode($next);
        }

        return $lignes;
    }

    /**
     * Lignes des seules fiches cochées, pour l'export d'une sélection : mêmes
     * colonnes et hydratation que la liste, sans parcourir le filtre courant.
     *
     * @param list<string> $ids ULID texte
     *
     * @return list<ReferentielLigne>
     */
    public function lignesPourIds(array $ids): array
    {
        $binaires = [];
        foreach ($ids as $id) {
            try {
                $binaires[] = Ulid::fromString($id)->toBinary();
            } catch (\InvalidArgumentException) {
            }
        }

        return $this->lignes($this->repository->rowsPourIds($binaires));
    }

    /** @return array{list<ReferentielLigne>, ?string} */
    private function page(ReferentielFiltres $filtres, ?FicheCursor $cursor, int $limit): array
    {
        ['rows' => $rows, 'hasNext' => $hasNext] = $this->repository->pageRows($filtres, $cursor, $limit);
        $lignes = $this->lignes($rows);
        $derniere = [] === $lignes ? null : $lignes[array_key_last($lignes)];

        return [
            $lignes,
            $hasNext && null !== $derniere
                ? (new FicheCursor($derniere->updatedAt, Ulid::fromString($derniere->id)))->encode()
                : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<ReferentielLigne>
     */
    private function lignes(array $rows): array
    {
        $binaires = array_map(static fn (array $row): string => (string) $row['id'], $rows);
        $auteurs = $this->repository->auteurs($binaires);
        $vignettes = $this->repository->vignetteStorageKeys($binaires);
        $marques = $this->repository->marquesIa($binaires);

        $lignes = [];
        foreach ($rows as $row) {
            $binaire = (string) $row['id'];
            $status = StatutFiche::from((string) $row['status']);
            $lignes[] = new ReferentielLigne(
                id: (string) Ulid::fromBinary($binaire),
                type: TypeFiche::from((string) $row['type']),
                code: (int) $row['code'],
                label: null === $row['label'] ? null : (string) $row['label'],
                ville: null === $row['ville'] ? null : (string) $row['ville'],
                status: $status,
                completeness: null === $row['completeness'] ? null : (int) $row['completeness'],
                canaux: (int) $row['canaux'],
                updatedAt: new \DateTimeImmutable((string) $row['updated_at']),
                auteur: $auteurs[$binaire] ?? null,
                contributeur: null === ($row['contributeur'] ?? null) ? null : (string) $row['contributeur'],
                vignette: isset($vignettes[$binaire]) ? $this->publicUrl->url($vignettes[$binaire]) : null,
                marqueIa: isset($marques[$binaire]),
                typologie: null === ($row['typologie'] ?? null) ? null : (string) $row['typologie'],
                pays: null === ($row['pays'] ?? null) ? null : (string) $row['pays'],
                // Pas de drapeau « actif » en donnée : une fiche archivée est
                // la seule éteinte du point de vue du référentiel.
                actif: StatutFiche::Archivee !== $status,
                premium: (bool) ($row['premium'] ?? false),
            );
        }

        return $lignes;
    }
}
