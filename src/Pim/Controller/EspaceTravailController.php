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
 * Espace de travail personnel. Les vues suivent les rôles du CDC — Éditeur,
 * Validateur, Administrateur — et chacun n'accède qu'aux vues que son rôle
 * couvre, la plus large étant proposée par défaut.
 */
final class EspaceTravailController extends AbstractController
{
    #[Route('/espace-de-travail', name: 'app_mdm_espace_travail', methods: ['GET'])]
    public function index(Request $request, CurrentActorProvider $actor, EspaceTravailEcran $ecran): Response
    {
        $autorisees = [];
        foreach (EspaceTravailEcran::ROLE_PAR_VUE as $vue => $role) {
            if ($this->isGranted($role)) {
                $autorisees[] = $vue;
            }
        }
        if ([] === $autorisees) {
            $autorisees = ['editeur'];
        }

        $vue = $request->query->getString('vue');
        if (!\in_array($vue, $autorisees, true)) {
            // La vue la plus large du rôle courant, comme point d'entrée.
            $vue = $autorisees[array_key_last($autorisees)];
        }

        return $this->render('mdm/espace_travail.html.twig', $ecran->variables($actor->id(), $vue, $autorisees));
    }
}
