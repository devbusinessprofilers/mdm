<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Dashboard\Repository\QualiteRepository;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\EspaceTravailRepository;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\RessourceLieuRepository;
use App\Pim\Repository\SavedViewRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Assemble l'espace de travail : la vue Supply (mes fiches, mes priorités,
 * mon activité) et la vue Administrateur (intégrité du référentiel, comptée
 * sur les mêmes règles que les écrans Qualité et Médias). La vue Chef de
 * projet attend ses mécanismes (consultations, favoris, signalements).
 */
final readonly class EspaceTravailEcran
{
    public const ROLE_PAR_DEFAUT = 'supply';

    /** @var array<string, string> */
    public const ROLES = [
        'supply' => 'Supply',
        'admin' => 'Administrateur',
        'cp' => 'Chef de projet',
    ];

    public function __construct(
        private EspaceTravailRepository $repository,
        private SavedViewRepository $vues,
        private QualiteRepository $qualite,
        private FicheRepository $fiches,
        private RessourceLieuRepository $ressources,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** @return array<string, mixed> Variables du gabarit mdm/espace_travail.html.twig. */
    public function variables(string $userId, string $role = self::ROLE_PAR_DEFAUT): array
    {
        $priorites = [];
        foreach ($this->repository->priorites($userId) as $priorite) {
            $type = TypeFiche::tryFrom($priorite['type']);
            $priorites[] = $priorite + ['url' => match ($type) {
                TypeFiche::Lieu => $this->urls->generate('app_mdm_fiche_lieu', ['id' => $priorite['id']]),
                TypeFiche::Restaurant, TypeFiche::Activite, TypeFiche::ServiceEvenementiel => $this->urls->generate('app_mdm_fiche_gamme', [
                    'gamme' => FicheEditeurEcran::slug($type),
                    'id' => $priorite['id'],
                ]),
                default => null,
            }];
        }
        $vuesEnregistrees = [];
        foreach ($this->vues->findVisiblesPour($userId) as $vue) {
            $vuesEnregistrees[] = [
                'vue' => $vue,
                'url' => $this->urls->generate('app_mdm_referentiel_general', ['f' => $vue->filters()]),
            ];
        }
        $badges = $this->qualite->badges();

        return [
            'role' => $role,
            'roles' => self::ROLES,
            'compteurs' => $this->repository->compteurs($userId),
            'priorites' => $priorites,
            'activite' => $this->repository->activite($userId, new \DateTimeImmutable('-7 days')),
            'vues_enregistrees' => $vuesEnregistrees,
            'url_mes_fiches' => $this->urls->generate('app_mdm_referentiel_general', [
                'f' => ['contributeurs' => [$userId]],
            ]),
            'url_en_attente' => $this->urls->generate('app_mdm_referentiel_general', [
                'f' => ['contributeurs' => [$userId], 'statuts' => ['en_attente_validation']],
            ]),
            // Le rail « Contrôle » : les anomalies à arbitrer, comptées comme
            // sur l'écran Qualité.
            'anomalies' => $badges['conflits'],
            'integrite' => 'admin' === $role ? $this->integrite($badges) : null,
        ];
    }

    /**
     * La vue Administrateur : les règles d'intégrité réelles du référentiel,
     * comptées comme sur les écrans Qualité et Médias.
     *
     * @param array<string, int> $badges
     *
     * @return array<string, mixed>
     */
    private function integrite(array $badges): array
    {
        $formes = $this->qualite->ecartsDeForme();
        $sansPhoto = $this->fiches->countPublishedWithoutPhoto();
        $droitsInvalides = $this->ressources->countPublishedRightsIssues();

        $regles = [
            ['Règle · Publiée sans photographie', $sansPhoto, 'fiches publiées sans média rattaché', 'Bloquante',
                $this->urls->generate('app_mdm_medias', ['filter' => 'published_no_photo'])],
            ['Règle · Droits invalides sur publiées', $droitsInvalides, 'photos aux droits absents ou expirés', 'Bloquante',
                $this->urls->generate('app_mdm_medias', ['filter' => 'published_rights'])],
            ['Forme · Localisation sans pays', $formes['sans_pays'], 'fiches sans pays renseigné', 'Majeure',
                $this->urls->generate('app_mdm_qualite', ['onglet' => 'formes'])],
            ['Forme · Localisation sans GPS', $formes['sans_gps'], 'fiches sans coordonnées', 'Majeure',
                $this->urls->generate('app_mdm_qualite', ['onglet' => 'formes'])],
            ['Forme · Fiche sans libellé', $formes['sans_libelle'], 'fiches sans nom exploitable', 'Majeure',
                $this->urls->generate('app_mdm_qualite', ['onglet' => 'formes'])],
            ['Suggestions en attente', $badges['conflits'], 'valeurs IA et adresses à arbitrer', 'Mineure',
                $this->urls->generate('app_mdm_qualite', ['onglet' => 'conflits'])],
        ];

        $lignes = [];
        foreach ($regles as [$regle, $compte, $motif, $severite, $url]) {
            $lignes[] = [
                'regle' => $regle,
                'compte' => $compte,
                'motif' => $motif,
                'severite' => $severite,
                'url' => $url,
            ];
        }

        return ['anomalies' => $badges['conflits'] + $badges['formes'], 'lignes' => $lignes];
    }
}
