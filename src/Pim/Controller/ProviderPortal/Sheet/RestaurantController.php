<?php

namespace App\Pim\Controller\ProviderPortal\Sheet;

use App\Pim\Form\ProviderPortal\Invoicing\InvoicingType;
use App\Pim\Form\ProviderPortal\Media\LibraryType;
use App\Pim\Form\ProviderPortal\Sheet\Restaurant\RestaurantCapacityType;
use App\Pim\Form\ProviderPortal\Sheet\Restaurant\RestaurantCsrType;
use App\Pim\Form\ProviderPortal\Sheet\Restaurant\RestaurantFacilityType;
use App\Pim\Form\ProviderPortal\Sheet\Restaurant\RestaurantInformationType;
use App\Pim\Form\ProviderPortal\Sheet\Restaurant\RestaurantLocalisationType;
use App\Pim\Form\ProviderPortal\Sheet\Restaurant\RestaurantPricesType;
use App\Pim\Form\ProviderPortal\Template\TemplateListType;
use App\Pim\Model\ProviderPortal\DTO\Invoicing\InvoicingDTO;
use App\Pim\Model\ProviderPortal\DTO\Media\LibraryDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant\RestaurantCapacityDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant\RestaurantCsrDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant\RestaurantFacilityDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant\RestaurantInformationDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant\RestaurantLocalisationDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Restaurant\RestaurantPricesDTO;
use App\Pim\Model\ProviderPortal\DTO\Template\TemplateListDTO;
use App\Pim\Model\ProviderPortal\Form\Media\LibraryConfiguration;
use App\Pim\Model\ProviderPortal\Mock\Provider\SheetProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RestaurantController extends AbstractController
{
    #[Route(path: 'portal/sheets/restaurants/{slug}/information', name: 'provider_portal_sheet_restaurant_index')]
    public function index(Request $request, string $slug): Response
    {
        $formOptions = [];

        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        if ($isPatch) {
            $formOptions['method'] = Request::METHOD_PATCH;
            $formOptions['validation_groups'] = false;
        }

        $form = $this->createForm(RestaurantInformationType::class, RestaurantInformationDTO::mock(), $formOptions);
        $form->handleRequest($request);

        if (!$isPatch && $form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/restaurant/information.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/restaurants/{slug}/localisation', name: 'provider_portal_sheet_restaurant_localisation')]
    public function localisation(Request $request, string $slug): Response
    {
        $formOptions = [];

        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        if ($isPatch) {
            $formOptions['method'] = Request::METHOD_PATCH;
            $formOptions['validation_groups'] = false;
        }

        $form = $this->createForm(RestaurantLocalisationType::class, RestaurantLocalisationDTO::mock(), $formOptions);
        $form->handleRequest($request);

        if (!$isPatch && $form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/restaurant/localisation.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/restaurants/{slug}/capacity', name: 'provider_portal_sheet_restaurant_capacity')]
    public function capacity(Request $request, string $slug): Response
    {
        $form = $this->createForm(RestaurantCapacityType::class, RestaurantCapacityDTO::mock());
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/online/sheet/restaurant/capacity.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/restaurants/{slug}/facility', name: 'provider_portal_sheet_restaurant_facility')]
    public function facility(Request $request, string $slug): Response
    {
        $form = $this->createForm(RestaurantFacilityType::class, RestaurantFacilityDTO::mock());
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/online/sheet/restaurant/facility.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/restaurants/{slug}/csr', name: 'provider_portal_sheet_restaurant_csr')]
    public function csr(Request $request, string $slug): Response
    {
        $form = $this->createForm(RestaurantCsrType::class, RestaurantCsrDTO::mock());
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/online/sheet/restaurant/csr.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/restaurants/{slug}/price', name: 'provider_portal_sheet_restaurant_price')]
    public function price(Request $request, string $slug): Response
    {
        $formOptions = [];

        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        if ($isPatch) {
            $formOptions['method'] = Request::METHOD_PATCH;
            $formOptions['validation_groups'] = false;
        }

        $form = $this->createForm(RestaurantPricesType::class, RestaurantPricesDTO::mock(), $formOptions);
        $form->handleRequest($request);

        if (!$isPatch && $form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/restaurant/prices.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/restaurants/{slug}/media', name: 'provider_portal_sheet_restaurant_media')]
    public function media(Request $request, string $slug): Response
    {
        $form = $this->createForm(LibraryType::class, LibraryDTO::mock(), [
            'library_configuration' => LibraryConfiguration::forPlace(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/restaurant/media.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/restaurants/{slug}/visibility', name: 'provider_portal_sheet_restaurant_visibility')]
    public function visibility(string $slug): Response
    {
        return $this->render('provider_portal/pages/online/sheet/restaurant/visibility.html.twig', [
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/restaurants/{slug}/invoicing', name: 'provider_portal_sheet_restaurant_invoicing')]
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

    #[Route(path: 'portal/sheets/restaurants/{slug}/template', name: 'provider_portal_sheet_restaurant_template')]
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
            'desktopMenu' => 'Menu:Sheet:Restaurant',
            'mobileMenu' => 'Menu:Sheet:Restaurant:MobileMenu',
            'sheet' => SheetProvider::getSheet($slug),
        ];
    }
}
