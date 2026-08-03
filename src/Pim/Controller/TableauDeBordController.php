<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Maquette\TableauDeBordMaquette;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écran d'accueil du back-office : « Tableau de bord ».
 *
 * Intégration de la maquette uniquement. Les cinq états du handoff — nominale,
 * zone 1 vide, volume d'alertes élevé, chargement progressif, paramétrage — se
 * choisissent par la query string, comme le sélecteur de la maquette.
 */
final class TableauDeBordController extends AbstractController
{
    /*
     * C'est la page d'accueil : la racine est le chemin canonique, celui que
     * `path('app_mdm_tableau_de_bord')` engendre. `/tableau-de-bord` reste servi
     * pour ne pas casser les liens déjà partagés.
     */
    #[Route('/', name: 'app_mdm_tableau_de_bord', methods: ['GET'])]
    #[Route('/tableau-de-bord', name: 'app_mdm_tableau_de_bord_alias', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $etat = TableauDeBordMaquette::etatValide($request->query->getString('etat'));
        $periode = TableauDeBordMaquette::periodeValide($request->query->getString('periode'));
        $croisement = $request->query->getString('croisement');
        // Le rail suit le défilement ; la query string ne sert qu'à ouvrir
        // l'écran sur une zone donnée.
        $zone = max(0, min(3, $request->query->getInt('zone')));

        return $this->render('mdm/tableau_de_bord.html.twig', [
            'etats' => TableauDeBordMaquette::etats($etat),
            'vue' => TableauDeBordMaquette::vue($etat, $periode, $croisement, $zone),
        ]);
    }
}
