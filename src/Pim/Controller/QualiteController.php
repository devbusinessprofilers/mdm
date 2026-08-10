<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Maquette\QualiteMaquette;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écran « Qualité » — Data Governance Workspace.
 *
 * Intégration de la maquette uniquement. Les cinq onglets se choisissent par la
 * query string, comme le rail du prototype.
 */
final class QualiteController extends AbstractController
{
    #[Route('/qualite', name: 'app_mdm_qualite', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $onglet = QualiteMaquette::ongletValide($request->query->getString('onglet'));

        return $this->render('mdm/qualite.html.twig', [
            'vue' => QualiteMaquette::vue($onglet),
        ]);
    }
}
