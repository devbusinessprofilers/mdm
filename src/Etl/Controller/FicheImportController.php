<?php

declare(strict_types=1);

namespace App\Etl\Controller;

use App\Audit\AuditContext;
use App\Etl\Entity\FicheImportJob;
use App\Etl\Form\FicheImportUploadType;
use App\Etl\Repository\FicheImportJobErrorRepository;
use App\Etl\Repository\FicheImportJobRepository;
use App\Etl\Service\FicheImportJobManager;
use App\Pim\Enum\TypeFiche;
use App\Pim\Import\FicheImportTemplateGenerator;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

#[Route('/admin/import-fiches', name: 'app_etl_import_')]
#[IsGranted('ROLE_ADMIN')]
final class FicheImportController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        FicheImportJobRepository $jobs,
        FicheImportJobManager $manager,
        AuditContext $audit,
    ): Response {
        $form = $this->createForm(FicheImportUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            $type = $data['type'];
            $file = $data['file'];
            if ($type instanceof TypeFiche && $file instanceof UploadedFile) {
                try {
                    $job = $manager->createFromUpload($file, $type, $audit->current()['actor']);
                    $this->addFlash('success', 'Import créé : le fichier sera traité en arrière-plan.');

                    return $this->redirectToRoute('app_etl_import_show', ['id' => $job->idString()]);
                } catch (\DomainException $exception) {
                    $form->get('file')->addError(new FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('etl/import/index.html.twig', [
            'form' => $form,
            'jobs' => $jobs->findRecent(),
            'types' => FicheImportSchemaRegistry::supportedTypes(),
        ]);
    }

    #[Route('/modele/{type}', name: 'template', requirements: ['type' => 'lieu|activite|restaurant|service_evenementiel'], methods: ['GET'])]
    public function template(string $type, FicheImportTemplateGenerator $generator): Response
    {
        $ficheType = TypeFiche::from($type);

        $path = tempnam(sys_get_temp_dir(), 'mdm-import-template');
        if (false === $path) {
            throw new \RuntimeException('Impossible de créer le fichier temporaire du modèle.');
        }
        $generator->write($ficheType, $path);

        $response = new BinaryFileResponse($path);
        $response->deleteFileAfterSend();
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $generator->filename($ficheType));

        return $response;
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET'])]
    public function show(
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
