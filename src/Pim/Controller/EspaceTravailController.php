<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Service\CurrentActorProvider;
use App\Pim\Service\EspaceTravailEcran;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace de travail personnel (vue Supply de la maquette) : ce qui m'est
 * assigné, mes priorités et mon activité. Les vues Administrateur et Chef de
 * projet attendront leurs prérequis (Salesforce, rôle CP).
 */
final class EspaceTravailController extends AbstractController
{
    #[Route('/espace-de-travail', name: 'app_mdm_espace_travail', methods: ['GET'])]
    public function index(CurrentActorProvider $actor, EspaceTravailEcran $ecran): Response
    {
        return $this->render('mdm/espace_travail.html.twig', $ecran->variables($actor->id()));
    }
}
