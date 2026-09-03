<?php

declare(strict_types=1);

namespace App\Etl\Controller;

use App\Account\Service\CurrentActorProvider;
use App\Dashboard\Repository\JournalTraitementsRepository;
use App\Etl\Form\ImportMasseUploadType;
use App\Etl\Repository\FicheImportJobRepository;
use App\Etl\Service\FicheImportJobManager;
use App\Pim\Export\FicheExportXlsxGenerator;
use App\Shared\Repository\FilesMessengerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Import en masse (Outils) : reprend un classeur issu de l'export du
 * référentiel. Une ligne avec un code met à jour la fiche liée en écrasant
 * ses valeurs (le fichier fait foi, cellule vide = champ vidé) ; une ligne
 * sans code crée une fiche ; les colonnes LOV acceptent les libellés du
 * classeur. Un job par feuille de gamme, suivi dans le journal /outils.
 */
#[IsGranted('ROLE_BP_VALIDATOR')]
final class ImportMasseController extends AbstractController
{
    #[Route('/outils/import-masse', name: 'app_etl_import_masse', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        CurrentActorProvider $actor,
        FicheImportJobManager $manager,
        FicheImportJobRepository $imports,
        JournalTraitementsRepository $journal,
        FilesMessengerRepository $files,
    ): Response {
        $form = $this->createForm(ImportMasseUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            if ($file instanceof UploadedFile) {
                try {
                    $jobs = $manager->createFromExportUpload(
                        $file,
                        $this->getUser()?->getUserIdentifier() ?? $actor->id(),
                    );
                } catch (\DomainException $exception) {
                    $this->addFlash('warning', $exception->getMessage());

                    return $this->redirectToRoute('app_etl_import_masse');
                }
                $this->addFlash('success', sprintf(
                    'Import en masse lancé : %d gamme(s) en file (%s). Suivi dans le journal ci-dessous.',
                    count($jobs),
                    implode(', ', array_map(static fn ($job): string => FicheExportXlsxGenerator::nomFeuille($job->type()), $jobs)),
                ));

                return $this->redirectToRoute('app_mdm_outils', ['famille' => 'import']);
            }
        }

        return $this->render('etl/import/masse.html.twig', [
            'form' => $form->createView(),
            'jobs' => $imports->findRecent(),
            // Rail Outils partagé (l'onglet Import en masse est courant) et
            // cartes de synthèse des files Messenger, comme sur le journal.
            'familles' => JournalTraitementsRepository::FAMILLES,
            'journal_limit' => JournalTraitementsRepository::JOURNAL_LIMIT,
            'outbox_en_attente' => $journal->outboxEnAttente(),
            'etat_files' => $files->etats(),
        ]);
    }
}
