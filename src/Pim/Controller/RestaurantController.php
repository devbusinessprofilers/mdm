<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Security\FicheVoter;
use App\Account\Service\CurrentActorProvider;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Form\FicheActionFormFactory;
use App\Pim\Repository\RestaurantRepository;
use App\Pim\Service\FicheWorkflowManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/referentiel/restaurants/fiche', name: 'app_pim_restaurant_')]
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
        if (!$restaurant instanceof Restaurant) {
            throw $this->createNotFoundException('Restaurant introuvable.');
        }
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
        if (!$restaurant instanceof Restaurant) {
            throw $this->createNotFoundException('Restaurant introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::SUBMIT, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'submit', 'Soumettre');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $errors = $workflow->submit($restaurant, $restaurant->fiche(), $actor->id());
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());

                return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'restaurants', 'id' => $restaurant->id()]);
            }
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
        if (!$restaurant instanceof Restaurant) {
            throw $this->createNotFoundException('Restaurant introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'validate', 'Validate');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $workflow->validate($restaurant->fiche(), $actor->id());
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

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
        if (!$restaurant instanceof Restaurant) {
            throw $this->createNotFoundException('Restaurant introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::PUBLISH, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'publish', 'Publier');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $workflow->publish($restaurant->fiche());
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

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
        if (!$restaurant instanceof Restaurant) {
            throw $this->createNotFoundException('Restaurant introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::ARCHIVE, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'archive', 'Archive');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $workflow->archive($restaurant->fiche(), $actor->id());
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'restaurants', 'id' => $restaurant->id()]);
    }

    #[Route('/{id}/desarchiver', name: 'unarchive', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function unarchive(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) {
            throw $this->createNotFoundException('Restaurant introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::ARCHIVE, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'unarchive', 'Désarchiver');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $workflow->unarchive($restaurant->fiche(), $actor->id());
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'restaurants', 'id' => $restaurant->id()]);
    }

    #[Route('/{id}/republier', name: 'republish', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function republish(
        Request $request,
        string $id,
        RestaurantRepository $repository,
        FicheActionFormFactory $forms,
        FicheWorkflowManager $workflow,
        CurrentActorProvider $actor,
    ): Response {
        $restaurant = $repository->find($id);
        if (!$restaurant instanceof Restaurant) {
            throw $this->createNotFoundException('Restaurant introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::PUBLISH, $restaurant->fiche());
        $form = $forms->action('restaurant', $restaurant->id(), 'republish', 'Republier');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $workflow->republish($restaurant->fiche(), $actor->id());
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

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
        if (!$restaurant instanceof Restaurant) {
            throw $this->createNotFoundException('Restaurant introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::VALIDATE, $restaurant->fiche());
        $form = $forms->reject('restaurant', $restaurant->id());
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('warning', 'Le motif du refus est obligatoire.');
        }
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $workflow->reject($restaurant->fiche(), $actor->id(), (string) $form->get('reason')->getData());
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_mdm_fiche_gamme', ['gamme' => 'restaurants', 'id' => $restaurant->id()]);
    }
}
