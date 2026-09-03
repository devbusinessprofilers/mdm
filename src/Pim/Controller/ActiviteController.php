<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Service\FicheWorkflowManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/referentiel/activites/fiche', name: 'app_pim_activite_')]
final class ActiviteController extends AbstractController
{
    #[Route(
        '/{id}/supprimer',
        name: 'delete',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
        methods: ['POST'],
    ),]
    public function delete(
        Request $request,
        string $id,
        ActiviteRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
    ): Response {
        $a = $repo->find($id);
        if (!$a instanceof Activite) {
            throw $this->createNotFoundException('Activité introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::DELETE, $a->fiche());
        $f = $forms->action('activite', $a->id(), 'delete', 'Supprimer');
        $f->handleRequest($request);
        if ($f->isSubmitted() && $f->isValid()) {
            $workflow->delete($a);
            $this->addFlash('success', 'Activité supprimée.');
        }

        return $this->redirectToRoute('app_mdm_referentiel_gamme', ['gamme' => 'activites']);
    }

    #[Route(
        '/{id}/soumettre',
        name: 'submit',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
        methods: ['POST'],
    ),]
    public function submit(
        Request $request,
        string $id,
        ActiviteRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $a = $repo->find($id);
        if (!$a instanceof Activite) {
            throw $this->createNotFoundException('Activité introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::SUBMIT, $a->fiche());
        $f = $forms->action('activite', $a->id(), 'submit', 'Soumettre');
        $f->handleRequest($request);
        if ($f->isSubmitted() && $f->isValid()) {
            try {
                $errors = $workflow->submit($a, $a->fiche(), $actor->id());
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());

                return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'activites', 'id' => $a->id()]);
            }
            if (count($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash(
                        'error',
                        $error->getPropertyPath().
                            ' : '.
                            $error->getMessage(),
                    );
                }

                return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'activites', 'id' => $a->id()]);
            }
        }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'activites', 'id' => $a->id()]);
    }

    #[Route(
        '/{id}/valider',
        name: 'validate',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
        methods: ['POST'],
    ),]
    public function validateActivity(
        Request $request,
        string $id,
        ActiviteRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $a = $repo->find($id);
        if (!$a instanceof Activite) {
            throw $this->createNotFoundException('Activité introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $a->fiche());
        $form = $forms->action('activite', $a->id(), 'validate', 'Validate');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $workflow->validate($a->fiche(), $actor->id());
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'activites', 'id' => $a->id()]);
    }

    #[Route(
        '/{id}/publier',
        name: 'publish',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
        methods: ['POST'],
    ),]
    public function publishActivity(
        Request $request,
        string $id,
        ActiviteRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
    ): Response {
        $a = $repo->find($id);
        if (!$a instanceof Activite) {
            throw $this->createNotFoundException('Activité introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::PUBLISH, $a->fiche());
        $form = $forms->action('activite', $a->id(), 'publish', 'Publier');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $workflow->publish($a->fiche());
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'activites', 'id' => $a->id()]);
    }

    #[Route(
        '/{id}/archiver',
        name: 'archive',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
        methods: ['POST'],
    ),]
    public function archive(
        Request $request,
        string $id,
        ActiviteRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $a = $repo->find($id);
        if (!$a instanceof Activite) {
            throw $this->createNotFoundException('Activité introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::ARCHIVE, $a->fiche());
        $form = $forms->action('activite', $a->id(), 'archive', 'Archive');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $workflow->archive($a->fiche(), $actor->id());
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'activites', 'id' => $a->id()]);
    }

    #[Route(
        '/{id}/desarchiver',
        name: 'unarchive',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
        methods: ['POST'],
    ),]
    public function unarchive(
        Request $request,
        string $id,
        ActiviteRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $a = $repo->find($id);
        if (!$a instanceof Activite) {
            throw $this->createNotFoundException('Activité introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::ARCHIVE, $a->fiche());
        $form = $forms->action('activite', $a->id(), 'unarchive', 'Désarchiver');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $workflow->unarchive($a->fiche(), $actor->id());
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'activites', 'id' => $a->id()]);
    }

    #[Route(
        '/{id}/republier',
        name: 'republish',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
        methods: ['POST'],
    ),]
    public function republish(
        Request $request,
        string $id,
        ActiviteRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $a = $repo->find($id);
        if (!$a instanceof Activite) {
            throw $this->createNotFoundException('Activité introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::PUBLISH, $a->fiche());
        $form = $forms->action('activite', $a->id(), 'republish', 'Republier');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $workflow->republish($a->fiche(), $actor->id());
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'activites', 'id' => $a->id()]);
    }

    #[Route(
        '/{id}/refuser',
        name: 'reject',
        requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
        methods: ['POST'],
    ),]
    public function reject(
        Request $request,
        string $id,
        ActiviteRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $a = $repo->find($id);
        if (!$a instanceof Activite) {
            throw $this->createNotFoundException('Activité introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $a->fiche());
        $f = $forms->reject('activite', $a->id());
        $f->handleRequest($request);
        if ($f->isSubmitted() && !$f->isValid()) {
            $this->addFlash('warning', 'Le motif du refus est obligatoire.');
        }
        if ($f->isSubmitted() && $f->isValid()) {
            try {
                $workflow->reject($a->fiche(), $actor->id(), (string) $f->get('reason')->getData());
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'activites', 'id' => $a->id()]);
    }
}
