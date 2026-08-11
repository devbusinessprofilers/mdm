<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Repository\JournalTraitementsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Vue admin des traitements en échec : les mêmes échecs que ceux comptés par
 * la ligne « Traitements en échec » du tableau de bord, avec le message
 * d'erreur de chacun. Les relances se font sur les écrans de détail liés.
 */
#[IsGranted('ROLE_ADMIN')]
final class TraitementsEnEchecController extends AbstractController
{
    #[Route('/admin/traitements-en-echec', name: 'app_dashboard_traitements_en_echec', methods: ['GET'])]
    public function __invoke(JournalTraitementsRepository $journal): Response
    {
        return $this->render('dashboard/traitements_en_echec.html.twig', [
            'lignes' => $journal->echecs(),
            'familles' => JournalTraitementsRepository::FAMILLES,
        ]);
    }
}
