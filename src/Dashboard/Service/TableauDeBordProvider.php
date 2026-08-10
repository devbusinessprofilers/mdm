<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Dashboard\Repository\ActivitePeriodeRepository;
use App\Dashboard\Repository\FilesATraiterRepository;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\FicheRouteResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Files « À traiter » du tableau de bord. La sévérité d'une file est un ratio
 * à son volume normal, jamais un absolu : seuils ×1,5 (attention) et ×3
 * (critique). Un traitement en échec est un état, pas un volume — toute
 * occurrence est critique.
 */
final readonly class TableauDeBordProvider
{
    /** Volumes de croisière provisoires (repris de la maquette) — à ajuster à l'usage. */
    private const VOLUMES_NORMAUX = [
        'a_valider' => 60,
        'a_publier' => 40,
        'ia' => 50,
        'repli' => 30,
    ];

    /** Périodes d'analyse de l'activité, comme la maquette. */
    public const PERIODES = [
        '7j' => ['libelle' => '7 jours', 'intervalle' => '-7 days'],
        '30j' => ['libelle' => '30 jours', 'intervalle' => '-30 days'],
        'trimestre' => ['libelle' => 'Trimestre', 'intervalle' => '-3 months'],
        'annee' => ['libelle' => 'Année', 'intervalle' => '-1 year'],
    ];

    public function __construct(
        private FilesATraiterRepository $repository,
        private ActivitePeriodeRepository $activite,
        private FicheRepository $fiches,
        private FicheRouteResolver $routes,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** @return list<array{fiche: Fiche, url: ?string}> Dernières publications, avec lien vers la fiche. */
    public function publications(int $limit = 5): array
    {
        return array_map(
            fn (Fiche $fiche): array => [
                'fiche' => $fiche,
                'url' => match (true) {
                    TypeFiche::Lieu === $fiche->type() => $this->urls->generate('app_mdm_fiche_lieu', ['id' => $fiche->idString()]),
                    TypeFiche::Traiteur === $fiche->type() => null,
                    default => $this->routes->showUrl($fiche->type(), $fiche->idString()),
                },
            ],
            $this->fiches->findDernieresPubliees($limit),
        );
    }

    public static function periodeValide(string $periode): string
    {
        return array_key_exists($periode, self::PERIODES) ? $periode : '7j';
    }

    /**
     * @return array{
     *     periode: string,
     *     libelle: string,
     *     creees: int,
     *     modifiees: int,
     *     parActeur: list<array{actor: string, creees: int, modifiees: int, validees: int, publiees: int}>
     * }
     */
    public function activite(string $periode): array
    {
        $periode = self::periodeValide($periode);
        $depuis = new \DateTimeImmutable(self::PERIODES[$periode]['intervalle'], new \DateTimeZone('Europe/Paris'));

        return [
            'periode' => $periode,
            'libelle' => self::PERIODES[$periode]['libelle'],
        ] + $this->activite->activite($depuis);
    }

    /** @return list<array{id: string, libelle: string, detail: string, volume: int, severite: string, url: string}> */
    public function files(): array
    {
        $comptes = $this->repository->comptes();

        return [
            [
                'id' => 'a_valider',
                'libelle' => 'Fiches à valider',
                'detail' => 'Prêtes pour relecture',
                'volume' => $comptes['a_valider'],
                'severite' => $this->severite('a_valider', $comptes['a_valider']),
                'url' => $this->urls->generate('app_mdm_referentiel_general', ['f' => ['statuts' => ['en_attente_validation']]]),
            ],
            [
                'id' => 'a_publier',
                'libelle' => 'Fiches à publier',
                'detail' => 'Validées non diffusées',
                'volume' => $comptes['a_publier'],
                'severite' => $this->severite('a_publier', $comptes['a_publier']),
                'url' => $this->urls->generate('app_mdm_referentiel_general', ['f' => ['statuts' => ['validee']]]),
            ],
            [
                'id' => 'ia',
                'libelle' => 'Valeurs IA à arbitrer',
                'detail' => 'Suggestions d\'extraction en attente',
                'volume' => $comptes['ia'],
                'severite' => $this->severite('ia', $comptes['ia']),
                'url' => $this->urls->generate('app_mdm_referentiel_general', ['f' => ['ia' => '1']]),
            ],
            [
                'id' => 'repli',
                'libelle' => 'Contacts de repli',
                'detail' => 'Fiches sans contact prestataire',
                'volume' => $comptes['repli'],
                'severite' => $this->severite('repli', $comptes['repli']),
                'url' => $this->urls->generate('app_mdm_referentiel_general', ['f' => ['repli' => '1']]),
            ],
            [
                'id' => 'echecs',
                'libelle' => 'Traitements en échec',
                'detail' => 'Imports, traductions, extractions, médias',
                'volume' => $comptes['echecs'],
                // Un échec est un état : toute occurrence est critique.
                'severite' => $comptes['echecs'] > 0 ? 'critique' : 'normale',
                'url' => $this->urls->generate('app_etl_import_index'),
            ],
        ];
    }

    private function severite(string $file, int $volume): string
    {
        $normal = self::VOLUMES_NORMAUX[$file];

        return match (true) {
            $volume > 3 * $normal => 'critique',
            $volume > 1.5 * $normal => 'attention',
            default => 'normale',
        };
    }
}
