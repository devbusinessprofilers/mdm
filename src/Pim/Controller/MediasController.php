<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Maquette\MediasMaquette;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écran « Médias » — le DAM.
 *
 * Intégration de la maquette uniquement. Les huit onglets se choisissent par la
 * query string, comme le rail du prototype.
 */
final class MediasController extends AbstractController
{
    #[Route('/medias', name: 'app_mdm_medias', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $onglet = MediasMaquette::ongletValide($request->query->getString('onglet'));

        return $this->render('mdm/medias.html.twig', [
            'vue' => MediasMaquette::vue($onglet),
        ]);
    }
}
