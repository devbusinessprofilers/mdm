<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Dam\Service\LieuPhotoPresenter;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\Form\LieuType;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\LieuRepository;
use App\Pim\Service\FicheCountProvider;
use App\Pim\Service\InternalFicheMutationPolicy;
use App\Pim\Service\LieuAdminManager;
use App\Pim\Service\LieuAdminViewBuilder;
use App\Pim\Service\FicheWorkflowManager;
use App\Shared\Search\SearchQuery;
use App\Shared\Service\SearchEngineInterface;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/referentiel/lieux/fiche', name: 'app_pim_lieu_')]
final class LieuController extends AbstractController
{
    #[
        Route(
            '/{id}/supprimer',
            name: 'delete',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function delete(
        Request $request,
        string $id,
        LieuRepository $repository,
        FicheActionFormFactory $forms,
        LieuAdminManager $manager,
    ): Response {
        $lieu = $repository->find($id);
        if (!$lieu instanceof Lieu) { $this->addFlash('warning', 'Ce lieu n’existe plus ou vient d’être supprimé.'); return $this->redirectToRoute('app_mdm_lieux'); }
        $this->denyAccessUnlessGranted(FicheVoter::DELETE, $lieu->fiche());

        $form = $forms->action('lieu', $lieu->id(), 'delete', 'Supprimer', true);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $manager->delete($lieu);
            $this->addFlash('success', 'Lieu supprimé.');
        }

        return $this->redirectToRoute('app_mdm_lieux');
    }

    #[
        Route(
            '/{id}/soumettre',
            name: 'submit',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function submit(
        Request $request,
        string $id,
        LieuRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $lieu = $repository->find($id);
        if (!$lieu instanceof Lieu) { throw $this->createNotFoundException('Lieu introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::SUBMIT, $lieu->fiche());
        $form = $forms->action('lieu', $lieu->id(), 'submit', 'Soumettre à validation');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $violations = $workflow->submit($lieu, $lieu->fiche(), $actor->id());
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $this->addFlash(
                        'error',
                        (string) $violation->getPropertyPath().
                            ' : '.
                            $violation->getMessage(),
                    );
                }

                return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $lieu->id()]);
            }
            $this->addFlash('success', 'Fiche soumise à validation.');
        }

        return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $lieu->id()]);
    }


    #[
        Route(
            '/{id}/valider',
            name: 'validate',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function validateLieu(
        Request $request,
        string $id,
        LieuRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $lieu = $repository->find($id);
        if (!$lieu instanceof Lieu) { throw $this->createNotFoundException('Lieu introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $lieu->fiche());
        $form = $forms->action('lieu', $lieu->id(), 'validate', 'Valider');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $workflow->validate($lieu->fiche(), $actor->id());
            $this->addFlash('success', 'Fiche validée.');
        }

        return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $lieu->id()]);
    }

    #[
        Route(
            '/{id}/publier',
            name: 'publish',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function publishLieu(
        Request $request,
        string $id,
        LieuRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
    ): Response {
        $lieu = $repository->find($id);
        if (!$lieu instanceof Lieu) { throw $this->createNotFoundException('Lieu introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::PUBLISH, $lieu->fiche());
        $form = $forms->action('lieu', $lieu->id(), 'publish', 'Publier');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $workflow->publish($lieu->fiche());
            $this->addFlash('success', 'Fiche publiée.');
        }

        return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $lieu->id()]);
    }

    #[
        Route(
            '/{id}/refuser',
            name: 'reject',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function reject(
        Request $request,
        string $id,
        LieuRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $lieu = $repository->find($id);
        if (!$lieu instanceof Lieu) { throw $this->createNotFoundException('Lieu introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $lieu->fiche());
        $form = $forms->reject('lieu', $lieu->id());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{reason: string} $data */
            $data = $form->getData();
            $workflow->reject($lieu->fiche(), $actor->id(), $data['reason']);
            $this->addFlash('success', 'Fiche refusée et renvoyée en cours.');
        }

        return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $lieu->id()]);
    }

    #[
        Route(
            '/{id}/archiver',
            name: 'archive',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            methods: ['POST'],
        ),
    ]
    public function archive(
        Request $request,
        string $id,
        LieuRepository $repository,
        FicheActionFormFactory $forms,
        LieuAdminManager $manager,
        CurrentActorProvider $actor,
    ): Response {
        $lieu = $repository->find($id);
        if (!$lieu instanceof Lieu) { throw $this->createNotFoundException('Lieu introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::ARCHIVE, $lieu->fiche());
        $form = $forms->action('lieu', $lieu->id(), 'archive', 'Archiver');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $manager->archive($lieu, $actor->id());
            $this->addFlash('success', 'Fiche archivée.');
        }

        return $this->redirectToRoute('app_mdm_fiche_lieu', ['id' => $lieu->id()]);
    }
}
