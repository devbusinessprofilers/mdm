<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Maquette\OutilsMaquette;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écran « Outils » : le journal des traitements.
 *
 * Intégration de la maquette uniquement. Les quatre outils partagent un rail ;
 * les trois autres n'ont pas encore de gabarit et pointent vers l'écran
 * d'attente. La bibliothèque de médias a sa propre entrée de barre.
 */
final class OutilsController extends AbstractController
{
    #[Route('/outils', name: 'app_mdm_outils', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('mdm/outils.html.twig', [
            'vue' => OutilsMaquette::vue(),
        ]);
    }
}
