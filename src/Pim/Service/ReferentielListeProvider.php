<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dam\Service\PublicMediaUrlGenerator;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TriReferentiel;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\ReferentielFiltres;
use App\Pim\ReadModel\ReferentielCursor;
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
        private RechercheCorrecteur $correcteur,
        private RechercheSuggestions $recherche,
    ) {
    }

    public function vue(ReferentielFiltres $filtres, ?ReferentielCursor $cursor = null, int $limit = 14): ReferentielVue
    {
        $limit = max(1, min(100, $limit));
        [$lignes, $nextCursor] = $this->page($filtres, $cursor, $limit);

        $correction = null;
        if ([] === $lignes) {
            foreach ($this->filtresCorriges($filtres) as $candidats) {
                [$lignesCorrigees, $cursorCorrige] = $this->page($candidats, $cursor, $limit);
                if ([] !== $lignesCorrigees) {
                    // Le total, les facettes et le curseur suivent la requête
                    // corrigée ; $filtres d'origine n'est jamais muté (badges,
                    // formulaires et vues enregistrées affichent la saisie).
                    $filtres = $candidats;
                    [$lignes, $nextCursor] = [$lignesCorrigees, $cursorCorrige];
                    $correction = $candidats->q;
                    break;
                }
            }
        }

        return new ReferentielVue(
            lignes: $lignes,
            nextCursor: $nextCursor,
            total: $this->repository->count($filtres),
            totalReferentiel: $this->repository->totalReferentiel(),
            comptes: $this->repository->comptes($filtres),
            paysChoices: $this->repository->paysChoices(),
            valeursChoices: $this->repository->valeursChoices($filtres),
            contributeursChoices: $this->repository->contributeursChoices($filtres),
            correction: $correction,
        );
    }

    /** @return list<string> Identifiants (ULID texte) de toutes les fiches du filtre, pour les actions groupées. */
    public function idsPourFiltre(ReferentielFiltres $filtres, int $plafond): array
    {
        $ids = $this->repository->idsPourFiltre($filtres, $plafond);
        if ([] === $ids) {
            // « Tout le résultat filtré » sur une liste affichée grâce à la
            // correction doit sélectionner ce que l'utilisateur voit.
            foreach ($this->filtresCorriges($filtres) as $candidats) {
                $ids = $this->repository->idsPourFiltre($candidats, $plafond);
                if ([] !== $ids) {
                    break;
                }
            }
        }

        return $ids;
    }

    /**
     * Clones des filtres portant chaque candidate de correction, de la plus
     * probable à la moins probable. Déterministe à saisie égale : la pagination
     * keyset recalcule et resonde à chaque page.
     *
     * @return list<ReferentielFiltres>
     */
    private function filtresCorriges(ReferentielFiltres $filtres): array
    {
        $q = trim((string) $filtres->q);
        if ('' === $q) {
            return [];
        }
        // La résolution par groupes d'abord (corrige plusieurs mots fautifs à
        // la fois via les noms de fiches), puis le sondage phrase par phrase
        // (attrape les fautes qui matchent hors nom : villes, descriptions).
        $phrases = $this->correcteur->corrections($q);
        $viaGroupes = $this->recherche->correction($q);
        if (null !== $viaGroupes) {
            array_unshift($phrases, $viaGroupes);
        }
        $clones = [];
        foreach (array_values(array_unique($phrases)) as $corrigee) {
            $candidats = clone $filtres;
            $candidats->q = $corrigee;
            $clones[] = $candidats;
        }

        return $clones;
    }

    /** @return array{precedente: ?string, suivante: ?string} */
    public function voisines(ReferentielFiltres $filtres, string $ficheId): array
    {
        return $this->repository->voisines($filtres, $ficheId);
    }

    /** @return array{list<ReferentielLigne>, ?string} */
    private function page(ReferentielFiltres $filtres, ?ReferentielCursor $cursor, int $limit): array
    {
        ['rows' => $rows, 'hasNext' => $hasNext] = $this->repository->pageRows($filtres, $cursor, $limit);
        $lignes = $this->lignes($rows);
        $derniere = [] === $lignes ? null : $lignes[array_key_last($lignes)];

        return [
            $lignes,
            $hasNext && null !== $derniere
                ? (new ReferentielCursor($filtres->tri, self::cleCursor($filtres->tri, $derniere), Ulid::fromString($derniere->id)))->encode()
                : null,
        ];
    }

    /** Valeur de la clé de tri sur la ligne, miroir exact des COALESCE SQL de ReferentielRepository::specTri(). */
    private static function cleCursor(TriReferentiel $tri, ReferentielLigne $ligne): string
    {
        return match ($tri->colonne()) {
            'nom' => $ligne->label ?? '',
            'gamme' => $ligne->type->value,
            'pays' => $ligne->pays ?? '',
            'statut' => $ligne->status->value,
            'completude' => (string) ($ligne->completeness ?? -1),
            'diffusion' => (string) $ligne->canaux,
            default => $ligne->updatedAt->format('Y-m-d H:i:s.u'),
        };
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
