<?php

declare(strict_types=1);

namespace App\Account\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écrans « Connexion & mot de passe » de l'espace prestataire.
 *
 * Intégration des maquettes uniquement : ces actions se contentent de rendre
 * les gabarits. Aucune authentification n'est branchée à ce stade, les
 * formulaires ne sont pas soumis à un traitement.
 */
final class AuthController extends AbstractController
{
    #[Route('/connexion', name: 'app_auth_connexion', methods: ['GET'])]
    public function connexion(): Response
    {
        return $this->render('auth/connexion.html.twig');
    }

    #[Route('/connexion/mot-de-passe', name: 'app_auth_mot_de_passe', methods: ['GET'])]
    public function motDePasse(): Response
    {
        return $this->render('auth/mot_de_passe.html.twig');
    }

    #[Route('/connexion/mot-de-passe-defaut', name: 'app_auth_mot_de_passe_defaut', methods: ['GET'])]
    public function motDePasseDefaut(): Response
    {
        return $this->render('auth/mot_de_passe_defaut.html.twig');
    }

    #[Route('/mot-de-passe-oublie', name: 'app_auth_mot_de_passe_oublie', methods: ['GET'])]
    public function motDePasseOublie(): Response
    {
        return $this->render('auth/mot_de_passe_oublie.html.twig');
    }

    #[Route('/creation-mot-de-passe', name: 'app_auth_creation_mot_de_passe', methods: ['GET'])]
    public function creationMotDePasse(): Response
    {
        return $this->render('auth/creation_mot_de_passe.html.twig');
    }
}
