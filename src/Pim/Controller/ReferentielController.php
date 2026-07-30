<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Maquette\ReferentielMaquette;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Listes de fiches du référentiel.
 *
 * Intégration des maquettes uniquement. Les données viennent de
 * {@see ReferentielMaquette} ; ni recherche, ni filtre, ni tri, ni pagination
 * ne sont fonctionnels à ce stade.
 */
final class ReferentielController extends AbstractController
{
    #[Route('/referentiel', name: 'app_mdm_referentiel_general', methods: ['GET'])]
    public function general(): Response
    {
        return $this->render('mdm/referentiel_general.html.twig', [
            'entete' => ReferentielMaquette::entete(true),
            'typologies' => ReferentielMaquette::typologies(),
            'colonnes' => ReferentielMaquette::colonnes(true),
            'filtres' => ReferentielMaquette::filtres(true),
            'lignes' => ReferentielMaquette::lignes(true),
            'pagination' => ReferentielMaquette::pagination(true),
        ] + self::editionRapide());
    }

    #[Route('/referentiel/lieux', name: 'app_mdm_lieux', methods: ['GET'])]
    public function lieux(): Response
    {
        return $this->render('mdm/lieux.html.twig', [
            'entete' => ReferentielMaquette::entete(false),
            'colonnes' => ReferentielMaquette::colonnes(false),
            'filtres' => ReferentielMaquette::filtres(false),
            'lignes' => ReferentielMaquette::lignes(false),
            'pagination' => ReferentielMaquette::pagination(false),
            'selection_label' => ReferentielMaquette::LIBELLE_SELECTION,
            'actions_groupees' => ReferentielMaquette::ACTIONS_GROUPEES,
        ] + self::editionRapide());
    }

    /**
     * Données de la modale d'édition rapide, partagées par les deux listes :
     * elles ne dépendent pas de la ligne, seul le contenu de l'en-tête et de
     * l'adresse en dépend et voyage par attributs sur le crayon.
     *
     * @return array<string, mixed>
     */
    private static function editionRapide(): array
    {
        return [
            'sites_groupes' => ReferentielMaquette::sitesGroupes(),
            'sites_compte' => ReferentielMaquette::sitesCompte(),
            'sites_puces' => ReferentielMaquette::puceSites(),
            'sites_decompte' => ReferentielMaquette::sitesDecompte(),
            'classifications' => [
                'lieu' => ReferentielMaquette::classification('lieu'),
                'restaurant' => ReferentielMaquette::classification('restaurant'),
            ],
            'gammes' => ReferentielMaquette::GAMMES,
            'suggestions_adresse' => ReferentielMaquette::SUGGESTIONS_ADRESSE,
            'requete_adresse' => ReferentielMaquette::REQUETE_ADRESSE,
            'modifications_en_cours' => ReferentielMaquette::MODIFICATIONS_EN_COURS,
        ];
    }
}
