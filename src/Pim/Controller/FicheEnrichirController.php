<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Pim\Entity\Fiche;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\Message\EnrichirFiche;
use App\Pim\Repository\FicheEnrichmentRunRepository;
use App\Pim\Repository\FicheRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Bouton « Enrichir ce qui manque » de l'éditeur de fiche : enfile le scan
 * asynchrone de toutes les sources d'enrichissement actives (Sirene, Geoapify,
 * DATAtourisme, Wikidata, BAN, IA) sur cette seule fiche. Les suggestions
 * produites s'arbitrent dans le bloc « Suggestions en attente ».
 */
final class FicheEnrichirController extends AbstractController
{
    #[Route('/referentiel/fiche/{id}/enrichir', name: 'app_pim_fiche_enrichir', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function __invoke(
        string $id,
        Request $request,
        FicheRepository $fiches,
        FicheActionFormFactory $formFactory,
        FicheEnrichmentRunRepository $runs,
        MessageBusInterface $bus,
    ): Response {
        $fiche = $fiches->find(Ulid::fromString($id));
        if (!$fiche instanceof Fiche) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $fiche);

        $form = $formFactory->enrichir($id);
        $form->handleRequest($request);
        $retour = $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_mdm_referentiel_general'));
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('warning', 'Enrichissement non confirmé (jeton invalide).');

            return $retour;
        }
        // La demande est tracée dès le clic : visible « en file » dans le
        // journal /outils, complétée par le worker avec le résultat par source.
        $run = $runs->demarrer($fiche);
        $bus->dispatch(new EnrichirFiche($id, $run->idString()));
        $this->addFlash('success', 'Enrichissement lancé : les suggestions apparaîtront dans « Suggestions en attente » (suivi dans Outils).');

        return $retour;
    }
}
