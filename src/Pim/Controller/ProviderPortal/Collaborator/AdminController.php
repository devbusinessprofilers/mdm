<?php

namespace App\Pim\Controller\ProviderPortal\Collaborator;

use App\Pim\Form\ProviderPortal\Sheet\Collaborator\CollaboratorType;
use App\Pim\Form\ProviderPortal\Sheet\Collaborator\CreateCollaboratorType;
use App\Pim\Model\ProviderPortal\DTO\Collaborator\CollaboratorDTO;
use App\Pim\Model\ProviderPortal\Mock\Provider\CollaboratorProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    public const ITEMS_PER_PAGE = 8;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    #[Route(path: 'portal/sheets/collaborator/list', name: 'provider_portal_collaborator_list', methods: ['GET', 'POST'])]
    public function list(Request $request): Response
    {
        // NOTE: create two named forms to avoid collision between both forms on the same page (desktop and mobile)
        // => ensure do not have the same id/name for both form fields
        $desktopForm = $this->formFactory->createNamed('desktop_create_collaborator', CreateCollaboratorType::class);
        $mobileForm = $this->formFactory->createNamed('mobile_create_collaborator', CreateCollaboratorType::class);

        $desktopForm->handleRequest($request);
        $mobileForm->handleRequest($request);

        $submittedForm = $desktopForm->isSubmitted() ? $desktopForm : ($mobileForm->isSubmitted() ? $mobileForm : null);
        if ($submittedForm?->isValid()) {
            return $this->redirectToRoute('provider_portal_collaborator_edit', [
                'email' => $submittedForm->getData()->email,
            ]);
        }

        return $this->render('provider_portal/pages/online/collaborator/admin-list.html.twig', [
            'total' => CollaboratorProvider::count(),
            'itemsPerPage' => self::ITEMS_PER_PAGE,
            'currentPage' => $request->query->getInt('page', 1),
            'desktopForm' => $desktopForm,
            'mobileForm' => $mobileForm,
        ]);
    }

    #[Route(path: 'portal/sheets/collaborator/edit/{email}', name: 'provider_portal_collaborator_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $email): Response
    {
        $collaborator = CollaboratorProvider::search($email);

        if (null === $collaborator) {
            $collaborator = new CollaboratorDTO();
            $collaborator->email = $email;
        }

        // NOTE: create two named forms to avoid collision between both forms on the same page (desktop and mobile)
        // => ensure do not have the same id/name for both form fields
        $desktopForm = $this->formFactory->createNamed('desktop_collaborator', CollaboratorType::class, $collaborator, [
            'for_desktop' => true,
            'with_memberships' => true,
        ]);
        $mobileForm = $this->formFactory->createNamed('mobile_collaborator', CollaboratorType::class, $collaborator, [
            'for_desktop' => false,
            'with_memberships' => true,
        ]);

        $desktopForm->handleRequest($request);
        $mobileForm->handleRequest($request);

        $submittedForm = $desktopForm->isSubmitted() ? $desktopForm : ($mobileForm->isSubmitted() ? $mobileForm : null);
        if ($submittedForm?->isValid()) {
            dump($submittedForm->getData());

            return $this->redirectToRoute('provider_portal_collaborator_list', ['page' => $request->query->getInt('page', 1)]);
        }

        return $this->render('provider_portal/pages/online/collaborator/admin-edit.html.twig', [
            'total' => CollaboratorProvider::count(),
            'itemsPerPage' => self::ITEMS_PER_PAGE,
            'currentPage' => $request->query->getInt('page', 1),
            'desktopForm' => $desktopForm,
            'mobileForm' => $mobileForm,
        ]);
    }

    #[Route(path: 'portal/sheets/collaborator/delete/{email}', name: 'provider_portal_collaborator_delete', methods: ['POST', 'DELETE'])]
    public function delete(string $email): Response
    {
        $this->addFlash('info', 'Collaborateur supprimé');

        dump($email);

        return $this->redirectToRoute('provider_portal_collaborator_list');
    }
}
