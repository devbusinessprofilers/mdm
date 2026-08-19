<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Service\FicheEditeurEcran;
use App\Pim\Service\GammeEntiteResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Bloc médias de l'éditeur de fiche (galerie de photos + tuiles de documents),
 * re-rendu seul après chaque action média : le contrôleur Stimulus medias-bloc
 * remplace le contenu du wrapper au lieu de recharger la page, la saisie du
 * formulaire principal n'est jamais perdue.
 */
final class FicheMediasBlocController extends AbstractController
{
    #[Route('/referentiel/lieux/fiche/{id}/medias/bloc', name: 'app_pim_lieu_medias_bloc', methods: ['GET'], requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
    public function lieu(Lieu $lieu, FicheEditeurEcran $ecran): Response
    {
        // Même visibilité que la page fiche, qui rend ce bloc d'office ; les
        // mutations gardent EDIT + CSRF sur leurs propres routes.
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $lieu->fiche());

        return $this->render('pim/lieu/_medias.html.twig', $ecran->medias($lieu)['vars']);
    }

    #[Route('/referentiel/{gamme}/fiche/{id}/medias/bloc', name: 'app_pim_gamme_medias_bloc', methods: ['GET'], requirements: ['gamme' => 'restaurants|activites|services', 'id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
    public function gamme(string $gamme, string $id, GammeEntiteResolver $resolver, FicheEditeurEcran $ecran): Response
    {
        $entite = $resolver->resolve($gamme, $id);
        if (null === $entite) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $entite->fiche());

        return $this->render('pim/_medias_gamme.html.twig', $ecran->medias($entite)['vars']);
    }
}
