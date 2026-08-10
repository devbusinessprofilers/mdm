<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Service\DashboardPageProvider;
use App\Dashboard\Service\TableauDeBordProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tableau de bord MDM, page d'accueil de l'application (maquette front) :
 * les files « À traiter » comptées en direct, la santé du référentiel et
 * l'activité issues des snapshots existants.
 */
final class TableauDeBordController extends AbstractController
{
    #[Route('/', name: 'app_mdm_tableau_de_bord', methods: ['GET'])]
    public function __invoke(Request $request, TableauDeBordProvider $files, DashboardPageProvider $stats): Response
    {
        $periode = TableauDeBordProvider::periodeValide($request->query->getString('periode'));
        $croisement = 'publiees' === $request->query->getString('croisement') ? 'publiees' : 'completude';

        return $this->render('dashboard/tableau_de_bord.html.twig', [
            'files' => $files->files(),
            'activite' => $files->activite($periode),
            'periodes' => TableauDeBordProvider::PERIODES,
            'croisement' => $croisement,
            'publications' => $files->publications(),
        ] + $stats->page());
    }
}
