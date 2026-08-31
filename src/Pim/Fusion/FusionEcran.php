<?php

declare(strict_types=1);

namespace App\Pim\Fusion;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\NatureRessource;
use App\Pim\Export\FicheExportColonnesCatalogue;
use App\Pim\Export\FicheExportValueReader;
use App\Pim\Form\FusionType;
use App\Pim\Import\Schema\ColumnDefinition;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use App\Account\Security\FicheVoter;
use App\Pim\Enum\StatutFiche;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\FicheRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Assemble l'écran de comparaison de deux fiches à fusionner : champs
 * divergents en libellés (lecture d'export), rangés par onglets de l'éditeur,
 * présélection « la plus récente l'emporte », et résumé des collections qui
 * seront fusionnées en union. Le contrôleur ne fait que déléguer et rendre.
 */
final readonly class FusionEcran
{
    private const GROUPE_AUTRES = 'Autres champs';

    public function __construct(
        private FicheImportSchemaRegistry $schemas,
        private FusionChampsCatalogue $catalogue,
        private FusionValeurLecteur $lecteur,
        private ChampRecenceProvider $recence,
        private FicheExportValueReader $exportReader,
        private FicheExportColonnesCatalogue $exportColonnes,
        private FicheAffiliationRepository $affiliations,
        private FicheRepository $fiches,
        private Security $security,
        private FormFactoryInterface $forms,
    ) {
    }

    /**
     * Résout et contrôle la paire de fiches de l'écran : existence, gammes
     * identiques et supportées, fiches encore en circulation, droits
     * d'édition de l'acteur sur les deux.
     *
     * @return array{0: Fiche, 1: Fiche}
     *
     * @throws \DomainException avec le message à afficher
     */
    public function paire(string $a, string $b): array
    {
        if ($a === $b) {
            throw new \DomainException('Choisissez deux fiches distinctes.');
        }
        $ficheA = $this->fiches->find(Ulid::fromString($a));
        $ficheB = $this->fiches->find(Ulid::fromString($b));
        if (!$ficheA instanceof Fiche || !$ficheB instanceof Fiche) {
            throw new \DomainException('Fiche introuvable.');
        }
        if ($ficheA->type() !== $ficheB->type() || !in_array($ficheA->type(), FicheImportSchemaRegistry::supportedTypes(), true)) {
            throw new \DomainException('Seules deux fiches d’une même gamme peuvent être fusionnées.');
        }
        if (StatutFiche::Archivee === $ficheA->status() || StatutFiche::Archivee === $ficheB->status()) {
            throw new \DomainException('Une fiche archivée ne peut pas être fusionnée : désarchivez-la d’abord.');
        }
        if (!$this->security->isGranted(FicheVoter::EDIT, $ficheA) || !$this->security->isGranted(FicheVoter::EDIT, $ficheB)) {
            throw new \DomainException('Vous n’avez pas les droits d’édition sur ces deux fiches.');
        }

        return [$ficheA, $ficheB];
    }

    /**
     * Champs dont les valeurs divergent entre les deux fiches, dans l'ordre
     * des onglets de l'éditeur, avec valeurs affichables et présélection.
     *
     * @return list<array{titre: string, champs: list<array{nom: string, libelle: string, valeur_a: string, valeur_b: string, date_a: ?\DateTimeImmutable, date_b: ?\DateTimeImmutable, preselection: string}>}>
     */
    public function groupesDivergents(Fiche $a, Fiche $b): array
    {
        $type = $a->type();
        $schema = $this->schemas->for($type);
        $aggregatA = $schema->findAggregateByFiche($a) ?? throw new \DomainException('La fiche n’a pas de détail de gamme.');
        $aggregatB = $schema->findAggregateByFiche($b) ?? throw new \DomainException('La fiche n’a pas de détail de gamme.');
        $lovChoices = $schema->lovChoices();

        $divergents = [];
        foreach ($this->catalogue->champsComparables($type) as $column) {
            $valeurA = $this->lecteur->native($column, $aggregatA, $a);
            $valeurB = $this->lecteur->native($column, $aggregatB, $b);
            if ($this->lecteur->identiques($valeurA, $valeurB)) {
                continue;
            }
            $auditPath = $this->catalogue->auditPath($type, $column);
            $divergents[$column->header] = [
                'nom' => $column->header,
                'libelle' => $column->header,
                'valeur_a' => $this->affichable($column, $aggregatA, $a, $lovChoices),
                'valeur_b' => $this->affichable($column, $aggregatB, $b, $lovChoices),
                'date_a' => $this->recence->derniereModification($a, $auditPath),
                'date_b' => $this->recence->derniereModification($b, $auditPath),
                'preselection' => $this->recence->preselection($a, $b, $auditPath, $valeurA, $valeurB),
            ];
        }

        // Rangement par onglets de l'éditeur, comme la modale d'export ; les
        // champs hors catalogue d'export (supplément Fiche) ferment la marche.
        $groupes = [];
        foreach ($this->exportColonnes->groupesPour($type) as $groupe) {
            $champs = [];
            foreach ($groupe['colonnes'] as $colonne) {
                if (isset($divergents[$colonne['libelle']])) {
                    $champs[] = $divergents[$colonne['libelle']];
                    unset($divergents[$colonne['libelle']]);
                }
            }
            if ([] !== $champs) {
                $groupes[] = ['titre' => $groupe['titre'], 'champs' => $champs];
            }
        }
        if ([] !== $divergents) {
            $groupes[] = ['titre' => self::GROUPE_AUTRES, 'champs' => array_values($divergents)];
        }

        return $groupes;
    }

    /**
     * Résumé des collections fusionnées en union (rien ne se perd), pour
     * information : l'écran n'y propose pas de choix.
     *
     * @return list<array{libelle: string, a: int, b: int}>
     */
    public function unions(Fiche $a, Fiche $b): array
    {
        $schema = $this->schemas->for($a->type());
        $aggregatA = $schema->findAggregateByFiche($a);
        $aggregatB = $schema->findAggregateByFiche($b);

        $unions = [
            ['libelle' => 'Photos', 'a' => $this->nbRessources($a, NatureRessource::Photo), 'b' => $this->nbRessources($b, NatureRessource::Photo)],
            ['libelle' => 'Documents', 'a' => $this->nbRessources($a, NatureRessource::Document), 'b' => $this->nbRessources($b, NatureRessource::Document)],
            ['libelle' => 'Collaborateurs', 'a' => $this->affiliations->count(['fiche' => $a]), 'b' => $this->affiliations->count(['fiche' => $b])],
            ['libelle' => 'Sites de diffusion', 'a' => count($a->siteDiffusionIds()), 'b' => count($b->siteDiffusionIds())],
        ];
        foreach ($schema->collections() as $collection) {
            $unions[] = [
                'libelle' => ucfirst(str_replace('_', ' ', $collection->prefix)).'s',
                'a' => null === $aggregatA ? 0 : count($aggregatA->{$collection->getter}()),
                'b' => null === $aggregatB ? 0 : count($aggregatB->{$collection->getter}()),
            ];
        }

        return $unions;
    }

    /** Côté présélectionné comme survivant : la fiche modifiée le plus récemment. */
    public function survivantDefaut(Fiche $a, Fiche $b): string
    {
        return $a->updatedAt() >= $b->updatedAt() ? 'a' : 'b';
    }

    /**
     * @param list<array{titre: string, champs: list<array{nom: string, preselection: string}>}> $groupes
     *
     * @return FormInterface<mixed>
     */
    public function formulaire(Fiche $a, Fiche $b, array $groupes, string $actionUrl): FormInterface
    {
        $champs = [];
        foreach ($groupes as $groupe) {
            foreach ($groupe['champs'] as $champ) {
                $champs[] = ['nom' => $champ['nom'], 'preselection' => $champ['preselection']];
            }
        }

        return $this->forms->createNamed('fusion', FusionType::class, null, [
            'action' => $actionUrl,
            'champs' => $champs,
            'survivant_defaut' => $this->survivantDefaut($a, $b),
            'version_a' => $a->version(),
            'version_b' => $b->version(),
        ]);
    }

    /** @param array<string, array<string, string>> $lovChoices */
    private function affichable(ColumnDefinition $column, object $aggregat, Fiche $fiche, array $lovChoices): string
    {
        $cellule = $this->exportReader->cellules($column, $aggregat, $fiche, $lovChoices)[0] ?? null;

        return null === $cellule || '' === (string) $cellule ? '—' : (string) $cellule;
    }

    private function nbRessources(Fiche $fiche, NatureRessource $nature): int
    {
        $nb = 0;
        foreach ($fiche->resources() as $resource) {
            if ($nature === $resource->nature()) {
                ++$nb;
            }
        }

        return $nb;
    }
}
