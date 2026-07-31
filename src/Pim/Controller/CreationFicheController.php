<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Maquette\CreationFicheMaquette;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tunnel de création d'une fiche.
 *
 * Intégration de la maquette uniquement : rien n'est enregistré. Les six états
 * de démonstration du handoff sont servis par `?etat=`, et `?gamme=` force la
 * gamme sans changer d'état — c'est ce que fait le clic sur une carte de gamme.
 */
final class CreationFicheController extends AbstractController
{
    #[Route('/referentiel/fiche/nouvelle', name: 'app_mdm_creation_fiche', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $etat = CreationFicheMaquette::etatValide($request->query->getString('etat', 'vierge'));
        $gamme = $request->query->getString('gamme');

        return $this->render('mdm/creation_fiche.html.twig', CreationFicheMaquette::vue($etat, $gamme) + [
            'etats' => CreationFicheMaquette::ETATS,
        ]);
    }
}
