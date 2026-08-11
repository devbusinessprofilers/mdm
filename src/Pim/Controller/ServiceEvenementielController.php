<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\Form\ServiceEvenementielType;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\ServiceEvenementielRepository;
use App\Pim\Service\FicheCountProvider;
use App\Pim\Service\InternalFicheMutationPolicy;
use App\Pim\Service\ServiceEvenementielAdminManager;
use App\Pim\Service\ServiceEvenementielAdminViewBuilder;
use App\Pim\Service\FicheWorkflowManager;
use App\Shared\Search\SearchQuery;
use App\Shared\Service\SearchEngineInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/referentiel/services/fiche", name: "app_pim_service_")]
final class ServiceEvenementielController extends AbstractController
{
    #[
        Route(
            "/{id}/supprimer",
            name: "delete",
            requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
            methods: ["POST"],
        ),
    ]
    public function delete(
        Request $request,
        string $id,
        ServiceEvenementielRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
    ): Response {
        $service = $repo->find($id);
        if (!$service instanceof ServiceEvenementiel) { throw $this->createNotFoundException('Service introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::DELETE, $service->fiche());
        $f = $forms->action('service', $service->id(), 'delete', 'Supprimer');
        $f->handleRequest($request);
        if ($f->isSubmitted() && $f->isValid()) {
            $workflow->delete($service);
            $this->addFlash("success", "Service supprimé.");
        }

        return $this->redirectToRoute('app_mdm_referentiel_gamme', ['gamme' => 'services']);
    }

    #[
        Route(
            "/{id}/soumettre",
            name: "submit",
            requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
            methods: ["POST"],
        ),
    ]
    public function submit(
        Request $request,
        string $id,
        ServiceEvenementielRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $service = $repo->find($id);
        if (!$service instanceof ServiceEvenementiel) { throw $this->createNotFoundException('Service introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::SUBMIT, $service->fiche());
        $f = $forms->action('service', $service->id(), 'submit', 'Soumettre');
        $f->handleRequest($request);
        if ($f->isSubmitted() && $f->isValid()) {
            $errors = $workflow->submit($service, $service->fiche(), $actor->id());
            if (count($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash(
                        "error",
                        $error->getPropertyPath() .
                            " : " .
                            $error->getMessage(),
                    );
                }

                return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'services', 'id' => $service->id()]);
            }
        }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'services', 'id' => $service->id()]);
    }

    #[
        Route(
            "/{id}/valider",
            name: "validate",
            requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
            methods: ["POST"],
        ),
    ]
    public function validateService(
        Request $request,
        string $id,
        ServiceEvenementielRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $service = $repo->find($id);
        if (!$service instanceof ServiceEvenementiel) { throw $this->createNotFoundException('Service introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $service->fiche());
        $form = $forms->action('service', $service->id(), 'validate', 'Validate');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { $workflow->validate($service->fiche(), $actor->id()); }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'services', 'id' => $service->id()]);
    }

    #[
        Route(
            "/{id}/publier",
            name: "publish",
            requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
            methods: ["POST"],
        ),
    ]
    public function publishService(
        Request $request,
        string $id,
        ServiceEvenementielRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
    ): Response {
        $service = $repo->find($id);
        if (!$service instanceof ServiceEvenementiel) { throw $this->createNotFoundException('Service introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::PUBLISH, $service->fiche());
        $form = $forms->action('service', $service->id(), 'publish', 'Publier');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { $workflow->publish($service->fiche()); }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'services', 'id' => $service->id()]);
    }

    #[
        Route(
            "/{id}/archiver",
            name: "archive",
            requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
            methods: ["POST"],
        ),
    ]
    public function archive(
        Request $request,
        string $id,
        ServiceEvenementielRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $service = $repo->find($id);
        if (!$service instanceof ServiceEvenementiel) { throw $this->createNotFoundException('Service introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::ARCHIVE, $service->fiche());
        $form = $forms->action('service', $service->id(), 'archive', 'Archive');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { $workflow->archive($service->fiche(), $actor->id()); }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'services', 'id' => $service->id()]);
    }

    #[
        Route(
            "/{id}/refuser",
            name: "reject",
            requirements: ["id" => "[0-9A-HJKMNP-TV-Z]{26}"],
            methods: ["POST"],
        ),
    ]
    public function reject(
        Request $request,
        string $id,
        ServiceEvenementielRepository $repo,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $service = $repo->find($id);
        if (!$service instanceof ServiceEvenementiel) { throw $this->createNotFoundException('Service introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $service->fiche());
        $f = $forms->reject('service', $service->id());
        $f->handleRequest($request);
        if ($f->isSubmitted() && $f->isValid()) {
            $workflow->reject($service->fiche(), $actor->id(), (string) $f->get('reason')->getData());
        }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'services', 'id' => $service->id()]);
    }

}
