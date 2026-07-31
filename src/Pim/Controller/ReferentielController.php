<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Maquette\ListeFichesMaquette;
use App\Pim\Maquette\ReferentielMaquette;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liste des fiches du référentiel.
 *
 * La maquette « Liste des fiches » remplace les deux écrans précédents — le
 * référentiel général et la page Lieux : un seul tableau, dont le panneau de
 * filtres porte la gamme. `/referentiel/lieux` reste servi et ouvre la liste
 * filtrée sur les Lieux, pour que les liens du rail continuent de marcher.
 *
 * Intégration de la maquette uniquement : ni recherche, ni tri, ni pagination.
 */
final class ReferentielController extends AbstractController
{
    #[Route('/referentiel', name: 'app_mdm_referentiel_general', methods: ['GET'])]
    public function liste(Request $request): Response
    {
        return $this->rendre($request->query->getString('etat', 'nominal'));
    }

    #[Route('/referentiel/lieux', name: 'app_mdm_lieux', methods: ['GET'])]
    public function lieux(): Response
    {
        return $this->rendre('lieux');
    }

    private function rendre(string $etat): Response
    {
        return $this->render('mdm/liste_fiches.html.twig', ListeFichesMaquette::vue($etat) + [
            'total_referentiel' => number_format(ListeFichesMaquette::TOTAL_REFERENTIEL, 0, ',', ' '),
        ] + self::editionRapide());
    }

    /**
     * Données de la modale d'édition rapide : elles ne dépendent pas de la
     * ligne, seul l'en-tête en dépend et voyage par attributs sur le crayon.
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
