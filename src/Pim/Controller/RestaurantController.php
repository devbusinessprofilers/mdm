<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\RestaurantType;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\RestaurantRepository;
use App\Pim\Service\FicheCountProvider;
use App\Pim\Service\InternalFicheMutationPolicy;
use App\Pim\Service\RestaurantAdminManager;
use App\Pim\Service\RestaurantAdminViewBuilder;
use App\Pim\Service\FicheWorkflowManager;
use App\Shared\Search\SearchQuery;
use App\Shared\Service\SearchEngineInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/restaurants', name: 'app_pim_restaurant_')]
final class RestaurantController extends AbstractController
{
    #[Route('/{id}/supprimer', name: 'delete', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function delete(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
    ): Response {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) { throw $this->createNotFoundException('Restaurant introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::DELETE, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'delete', 'Supprimer');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $workflow->delete($restaurant);
            $this->addFlash('success', 'Restaurant supprimé.');
        }

        return $this->redirectToRoute('app_mdm_referentiel_gamme', ['gamme' => 'restaurants']);
    }

    #[Route('/{id}/soumettre', name: 'submit', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function submit(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) { throw $this->createNotFoundException('Restaurant introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::SUBMIT, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'submit', 'Soumettre');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errors = $workflow->submit($restaurant, $restaurant->fiche(), $actor->id());
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash(
                        'error',
                        $error->getPropertyPath().' : '.$error->getMessage(),
                    );
                }

                return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'restaurants', 'id' => $restaurant->id()]);
            }

        }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'restaurants', 'id' => $restaurant->id()]);
    }

    #[Route('/{id}/valider', name: 'validate', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function validateRestaurant(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) { throw $this->createNotFoundException('Restaurant introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'validate', 'Validate');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { $workflow->validate($restaurant->fiche(), $actor->id()); }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'restaurants', 'id' => $restaurant->id()]);
    }

    #[Route('/{id}/publier', name: 'publish', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function publishRestaurant(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
    ): Response {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) { throw $this->createNotFoundException('Restaurant introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::PUBLISH, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'publish', 'Publier');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { $workflow->publish($restaurant->fiche()); }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'restaurants', 'id' => $restaurant->id()]);
    }

    #[Route('/{id}/archiver', name: 'archive', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function archive(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) { throw $this->createNotFoundException('Restaurant introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::ARCHIVE, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'archive', 'Archive');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { $workflow->archive($restaurant->fiche(), $actor->id()); }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'restaurants', 'id' => $restaurant->id()]);
    }

    #[Route('/{id}/refuser', name: 'reject', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function reject(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) { throw $this->createNotFoundException('Restaurant introuvable.'); }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $restaurant->fiche());
        $form = $forms->reject('restaurant', $restaurant->id());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $workflow->reject($restaurant->fiche(), $actor->id(), (string) $form->get('reason')->getData());
        }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'restaurants', 'id' => $restaurant->id()]);
    }

}
