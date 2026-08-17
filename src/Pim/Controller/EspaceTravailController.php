<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Service\CurrentActorProvider;
use App\Pim\Service\EspaceTravailEcran;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace de travail personnel. Trois vues comme la maquette : Supply (mes
 * fiches et mes priorités), Administrateur (intégrité du référentiel) et
 * Chef de projet (consultation — les mécanismes back arrivent avec le rôle).
 */
final class EspaceTravailController extends AbstractController
{
    #[Route('/espace-de-travail', name: 'app_mdm_espace_travail', methods: ['GET'])]
    public function index(Request $request, CurrentActorProvider $actor, EspaceTravailEcran $ecran): Response
    {
        $role = $request->query->getString('role');
        if (!array_key_exists($role, EspaceTravailEcran::ROLES)) {
            $role = EspaceTravailEcran::ROLE_PAR_DEFAUT;
        }

        return $this->render('mdm/espace_travail.html.twig', $ecran->variables($actor->id(), $role));
    }
}
