<?php

declare(strict_types=1);

namespace App\Etl\Controller;

use App\Etl\Entity\FicheImportJob;
use App\Etl\Repository\FicheImportJobErrorRepository;
use App\Etl\Repository\FicheImportJobRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

/**
 * Détail d'un import de fiches (progression, rapport d'erreurs ligne à
 * ligne). Hors /admin : l'onglet Outils → Import en masse et le journal des
 * traitements y renvoient, l'écran est en lecture seule.
 */
#[IsGranted('ROLE_BP_VALIDATOR')]
final class FicheImportDetailController extends AbstractController
{
    #[Route('/outils/imports/{id}', name: 'app_etl_import_show', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET'])]
    public function __invoke(
        string $id,
        Request $request,
        FicheImportJobRepository $jobs,
        FicheImportJobErrorRepository $errors,
    ): Response {
        $job = $jobs->find(Ulid::fromString($id));
        if (!$job instanceof FicheImportJob) {
            throw $this->createNotFoundException('Import introuvable.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 100;

        return $this->render('etl/import/show.html.twig', [
            'job' => $job,
            'errors' => $errors->findPageForJob($job, $limit, ($page - 1) * $limit),
            'errorTotal' => $errors->countForJob($job),
            'page' => $page,
            'limit' => $limit,
        ]);
    }
}
