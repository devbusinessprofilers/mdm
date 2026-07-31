<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vitrine des composants importés du portail prestataire.
 *
 * Elle sert de contrôle pendant la conversion des écrans : si un composant
 * rend ici, il rend partout.
 */
final class ComposantsController extends AbstractController
{
    #[Route('/composants', name: 'app_mdm_composants', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('mdm/composants.html.twig');
    }
}
