<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Maquette\EspaceTravailMaquette;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écran d'accueil du back-office : « Mon espace de travail ».
 *
 * Intégration de la maquette uniquement. Le contenu vient de
 * {@see EspaceTravailMaquette} et le rôle est choisi par la query string, en
 * attendant que l'utilisateur authentifié le porte.
 */
final class EspaceTravailController extends AbstractController
{
    #[Route('/espace-de-travail', name: 'app_mdm_espace_travail', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $role = $request->query->getString('role', EspaceTravailMaquette::ROLE_PAR_DEFAUT);

        if (!\array_key_exists($role, EspaceTravailMaquette::ROLES)) {
            $role = EspaceTravailMaquette::ROLE_PAR_DEFAUT;
        }

        return $this->render('mdm/espace_travail.html.twig', [
            'role' => $role,
            'roles' => EspaceTravailMaquette::ROLES,
            'vue' => EspaceTravailMaquette::pourRole($role),
        ]);
    }
}
