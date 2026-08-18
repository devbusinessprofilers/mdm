<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Service\LieuAdminViewBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Modales de paramètres des photos/documents d'un lieu, préchargées en
 * arrière-plan par le contrôleur Stimulus lieu-media juste après le chargement
 * de la page fiche : la page ne paie plus le rendu d'une modale + ses
 * formulaires par média, et le clic sur une vignette reste instantané.
 */
final class LieuMediaModalesController extends AbstractController
{
    #[Route('/referentiel/lieux/fiche/{id}/medias/modales', name: 'app_pim_lieu_media_modales', methods: ['GET'], requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
    public function __invoke(Lieu $lieu, LieuAdminViewBuilder $viewBuilder): Response
    {
        // Même visibilité que la page fiche, où ces modales étaient rendues
        // d'office ; les mutations gardent EDIT + CSRF sur leurs propres routes.
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $lieu->fiche());

        return $this->render('pim/lieu/_medias_modales.html.twig', $viewBuilder->modalesVars($lieu));
    }
}
