<?php

namespace App\Pim\Controller\ProviderPortal\Sheet;

use App\Pim\Form\ProviderPortal\Invoicing\InvoicingType;
use App\Pim\Form\ProviderPortal\Media\LibraryType;
use App\Pim\Form\ProviderPortal\Sheet\Activity\ActivityCapacityType;
use App\Pim\Form\ProviderPortal\Sheet\Activity\ActivityCsrType;
use App\Pim\Form\ProviderPortal\Sheet\Activity\ActivityDescriptionType;
use App\Pim\Form\ProviderPortal\Sheet\Activity\ActivityInformationType;
use App\Pim\Form\ProviderPortal\Sheet\Activity\ActivityLocalisationType;
use App\Pim\Form\ProviderPortal\Sheet\Activity\ActivityPriceType;
use App\Pim\Form\ProviderPortal\Template\TemplateListType;
use App\Pim\Model\ProviderPortal\DTO\Invoicing\InvoicingDTO;
use App\Pim\Model\ProviderPortal\DTO\Media\LibraryDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\ActivityCapacityDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\ActivityCsrDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\ActivityDescriptionDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\ActivityInformationDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\ActivityLocalisationDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Activity\ActivityPriceDTO;
use App\Pim\Model\ProviderPortal\DTO\Template\TemplateListDTO;
use App\Pim\Model\ProviderPortal\Form\Media\LibraryConfiguration;
use App\Pim\Model\ProviderPortal\Mock\Provider\SheetProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ActivityController extends AbstractController
{
    #[Route(path: 'portal/sheets/activities/{slug}/information', name: 'provider_portal_sheet_activity_index')]
    public function index(Request $request, string $slug): Response
    {
        $formOptions = [];

        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        if ($isPatch) {
            $formOptions['method'] = Request::METHOD_PATCH;
            $formOptions['validation_groups'] = false;
        }

        $form = $this->createForm(ActivityInformationType::class, ActivityInformationDTO::mock(), $formOptions);
        $form->handleRequest($request);

        if (!$isPatch && $form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/activity/information.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/activities/{slug}/localisation', name: 'provider_portal_sheet_activity_localisation')]
    public function localisation(Request $request, string $slug): Response
    {
        $formOptions = [];

        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        if ($isPatch) {
            $formOptions['method'] = Request::METHOD_PATCH;
            $formOptions['validation_groups'] = false;
        }

        $form = $this->createForm(ActivityLocalisationType::class, ActivityLocalisationDTO::mock(), $formOptions);
        $form->handleRequest($request);

        if (!$isPatch && $form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/activity/localisation.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/activities/{slug}/description', name: 'provider_portal_sheet_activity_description')]
    public function description(Request $request, string $slug): Response
    {
        $form = $this->createForm(ActivityDescriptionType::class, ActivityDescriptionDTO::mock());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/activity/description.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/activities/{slug}/capacity', name: 'provider_portal_sheet_activity_capacity')]
    public function capacity(Request $request, string $slug): Response
    {
        $form = $this->createForm(ActivityCapacityType::class, ActivityCapacityDTO::mock());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/activity/capacity.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/activities/{slug}/price', name: 'provider_portal_sheet_activity_price')]
    public function price(Request $request, string $slug): Response
    {
        $formOptions = [];

        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        if ($isPatch) {
            $formOptions['method'] = Request::METHOD_PATCH;
            $formOptions['validation_groups'] = false;
        }

        $form = $this->createForm(ActivityPriceType::class, ActivityPriceDTO::mock(), $formOptions);
        $form->handleRequest($request);

        if (!$isPatch && $form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/activity/price.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/activities/{slug}/media', name: 'provider_portal_sheet_activity_media')]
    public function media(Request $request, string $slug): Response
    {
        $form = $this->createForm(LibraryType::class, LibraryDTO::mock(), [
            'library_configuration' => LibraryConfiguration::forActivity(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/activity/media.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/activities/{slug}/csr', name: 'provider_portal_sheet_activity_csr')]
    public function csr(Request $request, string $slug): Response
    {
        $form = $this->createForm(ActivityCsrType::class, ActivityCsrDTO::mock());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/activity/csr.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/activities/{slug}/visibility', name: 'provider_portal_sheet_activity_visibility')]
    public function visibility(string $slug): Response
    {
        return $this->render('provider_portal/pages/online/sheet/activity/visibility.html.twig', [
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/activities/{slug}/invoicing', name: 'provider_portal_sheet_activity_invoicing')]
    public function invoicing(Request $request, string $slug): Response
    {
        $formOptions = [];

        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        if ($isPatch) {
            $formOptions['method'] = Request::METHOD_PATCH;
            $formOptions['validation_groups'] = false;
        }

        $form = $this->createForm(InvoicingType::class, InvoicingDTO::mock(), $formOptions);
        $form->handleRequest($request);

        if (!$isPatch && $form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/invoicing.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/activities/{slug}/template', name: 'provider_portal_sheet_activity_template')]
    public function template(Request $request, string $slug): Response
    {
        $form = $this->createForm(TemplateListType::class, TemplateListDTO::mock());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/template.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    private function mockCompletion(string $slug): array
    {
        return [
            'desktopMenu' => 'Menu:Sheet:Activity',
            'mobileMenu' => 'Menu:Sheet:Activity:MobileMenu',
            'sheet' => SheetProvider::getSheet($slug),
        ];
    }
}
