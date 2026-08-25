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

        // Journal borné aux 1000 traitements les plus récents, paginé par 50 ;
        // la page demandée est bornée à l'intervalle réel.
        $lignes = $journal->journal($famille, $erreurs);
        $total = count($lignes);
        $enErreur = count(array_filter($lignes, static fn (array $ligne): bool => in_array($ligne['statut'], JournalTraitementsRepository::STATUTS_ERREUR, true)));
        $pages = max(1, (int) ceil($total / JournalTraitementsRepository::PAR_PAGE));
        $page = min(max(1, $request->query->getInt('page', 1)), $pages);

        return $this->render('dashboard/outils.html.twig', [
            'lignes' => array_slice($lignes, ($page - 1) * JournalTraitementsRepository::PAR_PAGE, JournalTraitementsRepository::PAR_PAGE),
            'total' => $total,
            'en_erreur_total' => $enErreur,
            'page' => $page,
            'pages' => $pages,
            'familles' => JournalTraitementsRepository::FAMILLES,
            'famille' => $famille,
            'erreurs' => $erreurs,
            'outbox_en_attente' => $journal->outboxEnAttente(),
            'etat_files' => $journal->etatFilesMessenger(),
            'journal_limit' => JournalTraitementsRepository::JOURNAL_LIMIT,
        ]);
    }

    /**
     * Fragment des cartes de synthèse, rechargé seul (turbo-frame) toutes les
     * ~10 s pour suivre en direct l'état des files Messenger.
     */
    #[Route('/outils/indicateurs', name: 'app_mdm_outils_indicateurs', methods: ['GET'])]
    public function indicateurs(JournalTraitementsRepository $journal): Response
    {
        $response = $this->render('dashboard/_outils_indicateurs.html.twig', [
            'outbox_en_attente' => $journal->outboxEnAttente(),
            'etat_files' => $journal->etatFilesMessenger(),
        ]);
        // Fragment rafraîchi en continu : jamais servi depuis un cache.
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
