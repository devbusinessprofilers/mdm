<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Etl\Service\SalesforceSelectionSender;
use App\Pim\Entity\Fiche;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\Repository\FicheRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Envoi immédiat d'une fiche vers Salesforce depuis le bouton de l'éditeur
 * (CSV Produits, système de transition). Les salles suivent l'envoi nocturne.
 */
final class FicheSalesforceController extends AbstractController
{
    #[Route('/referentiel/fiche/{id}/salesforce', name: 'app_pim_fiche_salesforce', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function __invoke(
        string $id,
        Request $request,
        FicheRepository $fiches,
        FicheActionFormFactory $formFactory,
        SalesforceSelectionSender $sender,
    ): Response {
        $fiche = $fiches->find(Ulid::fromString($id));
        if (!$fiche instanceof Fiche) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $fiche);

        $form = $formFactory->salesforce($id);
        $form->handleRequest($request);
        $retour = $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_mdm_referentiel_general'));
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('warning', 'Envoi Salesforce non confirmé (jeton invalide).');

            return $retour;
        }
        try {
            $sender->envoyerFiche($fiche);
        } catch (\DomainException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $retour;
        }
        $this->addFlash('success', 'Fiche transmise à Salesforce.');

        return $retour;
    }
}
