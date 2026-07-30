<?php

namespace App\Pim\Controller\ProviderPortal\Collaborator;

use App\Pim\Enum\ProviderPortal\SheetTypeEnum;
use App\Pim\Form\ProviderPortal\Sheet\Collaborator\CreateCollaboratorType;
use App\Pim\Form\ProviderPortal\Sheet\Collaborator\MembershipType;
use App\Pim\Model\ProviderPortal\DTO\Collaborator\CollaboratorDTO;
use App\Pim\Model\ProviderPortal\DTO\Collaborator\MembershipDTO;
use App\Pim\Model\ProviderPortal\DTO\SheetDTO;
use App\Pim\Model\ProviderPortal\Mock\Collaborator\RoleChoices;
use App\Pim\Model\ProviderPortal\Mock\Provider\CollaboratorProvider;
use App\Pim\Model\ProviderPortal\Mock\Provider\MembershipProvider;
use App\Pim\Model\ProviderPortal\Mock\Provider\SheetProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SheetController extends AbstractController
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    #[Route(path: 'portal/sheets/membership/{slug}/list', name: 'provider_portal_sheet_membership_list', methods: ['GET', 'POST'])]
    public function list(Request $request, string $slug): Response
    {
        $sheet = SheetProvider::getSheet($slug);
        if (null === $sheet) {
            throw $this->createNotFoundException();
        }

        // NOTE: create two named forms to avoid collision between both forms on the same page (desktop and mobile)
        // => ensure do not have the same id/name for both form fields
        $desktopForm = $this->formFactory->createNamed('desktop_create_collaborator', CreateCollaboratorType::class);
        $mobileForm = $this->formFactory->createNamed('mobile_create_collaborator', CreateCollaboratorType::class);

        $desktopForm->handleRequest($request);
        $mobileForm->handleRequest($request);

        $submittedForm = $desktopForm->isSubmitted() ? $desktopForm : ($mobileForm->isSubmitted() ? $mobileForm : null);
        if ($submittedForm?->isValid()) {
            return $this->redirectToRoute('provider_portal_sheet_membership_edit', [
                'slug' => $slug,
                'email' => $submittedForm->getData()->email,
            ]);
        }

        return $this->render('provider_portal/pages/online/collaborator/sheet-list.html.twig', [
            'sheet' => $sheet,
            'roles' => array_flip(RoleChoices::getChoices()), // For translation only!
            'memberships' => MembershipProvider::findAll($sheet),
            'desktopForm' => $desktopForm,
            'mobileForm' => $mobileForm,
            ...$this->resolveSheetMenus($sheet->type),
        ]);
    }

    #[Route(path: 'portal/sheets/membership/{slug}/edit/{email}', name: 'provider_portal_sheet_membership_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $slug, string $email): Response
    {
        $sheet = SheetProvider::getSheet($slug);
        if (null === $sheet) {
            throw $this->createNotFoundException();
        }

        $memberships = MembershipProvider::findAll($sheet);
        $membership = $this->getOrCreateMembership($sheet, $memberships, $email);

        // NOTE: create two named forms to avoid collision between both forms on the same page (desktop and mobile)
        // => ensure do not have the same id/name for both form fields
        $desktopForm = $this->formFactory->createNamed('desktop_membership', MembershipType::class, $membership, [
            'mode' => MembershipType::MODE_MEMBERSHIP,
            'for_desktop' => true,
        ]);
        $mobileForm = $this->formFactory->createNamed('mobile_membership', MembershipType::class, $membership, [
            'mode' => MembershipType::MODE_MEMBERSHIP,
            'for_desktop' => false,
        ]);

        $desktopForm->handleRequest($request);
        $mobileForm->handleRequest($request);

        $submittedForm = $desktopForm->isSubmitted() ? $desktopForm : ($mobileForm->isSubmitted() ? $mobileForm : null);
        if ($submittedForm?->isValid()) {
            dump($submittedForm->getData());

            return $this->redirectToRoute('provider_portal_sheet_membership_list', ['slug' => $slug]);
        }

        return $this->render('provider_portal/pages/online/collaborator/sheet-edit.html.twig', [
            'sheet' => $sheet,
            'roles' => array_flip(RoleChoices::getChoices()), // For translation only!
            'memberships' => MembershipProvider::findAll($sheet),
            'desktopForm' => $desktopForm,
            'mobileForm' => $mobileForm,
            ...$this->resolveSheetMenus($sheet->type),
        ]);
    }

    #[Route(path: 'portal/sheets/membership/{slug}/delete/{email}', name: 'provider_portal_sheet_membership_delete', methods: ['POST', 'DELETE'])]
    public function delete(string $slug, string $email): Response
    {
        $this->addFlash('info', 'Affiliation supprimée');

        dump($slug, $email);

        return $this->redirectToRoute('provider_portal_sheet_membership_list', ['slug' => $slug]);
    }

    private function resolveSheetMenus(SheetTypeEnum $sheetType): array
    {
        return match ($sheetType) {
            SheetTypeEnum::PLACE => ['desktopMenu' => 'Menu:Sheet:Place', 'mobileMenu' => 'Menu:Sheet:Place:MobileMenu'],
            SheetTypeEnum::RESTAURANT => ['desktopMenu' => 'Menu:Sheet:Restaurant', 'mobileMenu' => 'Menu:Sheet:Restaurant:MobileMenu'],
            SheetTypeEnum::ACTIVITY => ['desktopMenu' => 'Menu:Sheet:Activity', 'mobileMenu' => 'Menu:Sheet:Activity:MobileMenu'],
            SheetTypeEnum::SERVICE => ['desktopMenu' => 'Menu:Sheet:Service', 'mobileMenu' => 'Menu:Sheet:Service:MobileMenu'],
            SheetTypeEnum::MEAL_TRAY => ['desktopMenu' => 'Menu:Sheet:MealTray', 'mobileMenu' => 'Menu:Sheet:MealTray:MobileMenu'],
        };
    }

    private function getOrCreateMembership(SheetDTO $currentSheet, array $memberships, string $collaboratorEmail): MembershipDTO
    {
        foreach ($memberships as $membership) {
            if (0 === strcasecmp($membership->collaborator->email, $collaboratorEmail)) {
                return $membership;
            }
        }

        $collaborator = CollaboratorProvider::search($collaboratorEmail);
        if (null === $collaborator) {
            $collaborator = new CollaboratorDTO();
            $collaborator->email = $collaboratorEmail;
        }

        $membership = new MembershipDTO();
        $membership->sheet = $currentSheet;
        $membership->collaborator = $collaborator;

        return $membership;
    }
}
