<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use App\Dashboard\Repository\JournalTraitementsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écran « Outils » de la maquette : le journal des traitements de fond.
 * Chaque ligne renvoie vers l'écran existant (détail d'import, revue OCR,
 * traductions, DAM) où vivent le détail et les relances.
 */
final class OutilsController extends AbstractController
{
    #[Route('/outils', name: 'app_mdm_outils', methods: ['GET'])]
    public function __invoke(Request $request, JournalTraitementsRepository $journal): Response
    {
        $famille = $request->query->getString('famille');
        $famille = array_key_exists($famille, JournalTraitementsRepository::FAMILLES) ? $famille : null;
        $erreurs = $request->query->getBoolean('erreurs');

        return $this->render('dashboard/outils.html.twig', [
            'lignes' => $journal->journal($famille, $erreurs),
            'familles' => JournalTraitementsRepository::FAMILLES,
            'famille' => $famille,
            'erreurs' => $erreurs,
            'outbox_en_attente' => $journal->outboxEnAttente(),
        ]);
    }
}
