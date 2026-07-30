<?php

namespace App\Pim\Controller\ProviderPortal\Sheet;

use App\Pim\Form\ProviderPortal\Invoicing\InvoicingType;
use App\Pim\Form\ProviderPortal\Media\LibraryType;
use App\Pim\Form\ProviderPortal\Sheet\Place\PlaceAccommodationType;
use App\Pim\Form\ProviderPortal\Sheet\Place\PlaceCateringType;
use App\Pim\Form\ProviderPortal\Sheet\Place\PlaceCsrType;
use App\Pim\Form\ProviderPortal\Sheet\Place\PlaceDescriptionType;
use App\Pim\Form\ProviderPortal\Sheet\Place\PlaceGeneralDataType;
use App\Pim\Form\ProviderPortal\Sheet\Place\PlaceLeisureType;
use App\Pim\Form\ProviderPortal\Sheet\Place\PlaceLocalisationType;
use App\Pim\Form\ProviderPortal\Sheet\Place\PlaceMeetingType;
use App\Pim\Form\ProviderPortal\Sheet\Place\PlacePricesType;
use App\Pim\Form\ProviderPortal\Sheet\Place\PlaceServicesType;
use App\Pim\Form\ProviderPortal\Sheet\Place\PlaceThematicType;
use App\Pim\Form\ProviderPortal\Template\TemplateListType;
use App\Pim\Model\ProviderPortal\DTO\Invoicing\InvoicingDTO;
use App\Pim\Model\ProviderPortal\DTO\Media\LibraryDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceAccommodationDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceCateringDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceCsrDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceDescriptionDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceGeneralDataDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceLeisureDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceLocalisationDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceMeetingDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlacePricesDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceServicesDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Place\PlaceThematicDTO;
use App\Pim\Model\ProviderPortal\DTO\Template\TemplateListDTO;
use App\Pim\Model\ProviderPortal\Form\Media\LibraryConfiguration;
use App\Pim\Model\ProviderPortal\Mock\Provider\SheetProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PlaceController extends AbstractController
{
    #[Route(path: 'portal/sheets/places/{slug}/general-information', name: 'provider_portal_sheet_place_index')]
    public function index(Request $request, string $slug): Response
    {
        $formOptions = [];

        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        if ($isPatch) {
            $formOptions['method'] = Request::METHOD_PATCH;
            $formOptions['validation_groups'] = false;
        }

        $form = $this->createForm(PlaceGeneralDataType::class, PlaceGeneralDataDTO::mock(), $formOptions);
        $form->handleRequest($request);

        if (!$isPatch && $form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/place/general-information.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/places/{slug}/accommodation', name: 'provider_portal_sheet_place_accommodation')]
    public function accommodation(Request $request, string $slug): Response
    {
        $formOptions = [];

        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        if ($isPatch) {
            $formOptions['method'] = Request::METHOD_PATCH;
            $formOptions['validation_groups'] = false;
        }

        $form = $this->createForm(PlaceAccommodationType::class, PlaceAccommodationDTO::mock(), $formOptions);
        $form->handleRequest($request);

        if (!$isPatch && $form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/place/accommodation.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/places/{slug}/csr', name: 'provider_portal_sheet_place_csr')]
    public function csr(Request $request, string $slug): Response
    {
        $form = $this->createForm(PlaceCsrType::class, PlaceCsrDTO::mock());
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/online/sheet/place/csr.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/places/{slug}/prices', name: 'provider_portal_sheet_place_prices')]
    public function prices(Request $request, string $slug): Response
    {
        $formOptions = [];

        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        if ($isPatch) {
            $formOptions['method'] = Request::METHOD_PATCH;
            $formOptions['validation_groups'] = false;
        }

        $form = $this->createForm(PlacePricesType::class, PlacePricesDTO::mock(), $formOptions);
        $form->handleRequest($request);

        if (!$isPatch && $form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/place/prices.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/places/{slug}/description', name: 'provider_portal_sheet_place_description')]
    public function description(Request $request, string $slug): Response
    {
        $form = $this->createForm(PlaceDescriptionType::class, PlaceDescriptionDTO::mock());
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/online/sheet/place/description.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/places/{slug}/leisure', name: 'provider_portal_sheet_place_leisure')]
    public function leisure(Request $request, string $slug): Response
    {
        $form = $this->createForm(PlaceLeisureType::class, PlaceLeisureDTO::mock());
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/online/sheet/place/leisure.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/places/{slug}/catering', name: 'provider_portal_sheet_place_catering')]
    public function catering(Request $request, string $slug): Response
    {
        $form = $this->createForm(PlaceCateringType::class, PlaceCateringDTO::mock());
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/online/sheet/place/catering.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/places/{slug}/services', name: 'provider_portal_sheet_place_services')]
    public function services(Request $request, string $slug): Response
    {
        $form = $this->createForm(PlaceServicesType::class, PlaceServicesDTO::mock());
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/online/sheet/place/services.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/places/{slug}/thematic', name: 'provider_portal_sheet_place_thematic')]
    public function thematic(Request $request, string $slug): Response
    {
        $form = $this->createForm(PlaceThematicType::class, PlaceThematicDTO::mock());
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/online/sheet/place/thematic.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/places/{slug}/localisation', name: 'provider_portal_sheet_place_localisation')]
    public function localisation(Request $request, string $slug): Response
    {
        $formOptions = [];

        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        if ($isPatch) {
            $formOptions['method'] = Request::METHOD_PATCH;
            $formOptions['validation_groups'] = false;
        }

        $form = $this->createForm(PlaceLocalisationType::class, PlaceLocalisationDTO::mock(), $formOptions);
        $form->handleRequest($request);

        if (!$isPatch && $form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/place/localisation.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/places/{slug}/meeting', name: 'provider_portal_sheet_place_meeting')]
    public function meeting(Request $request, string $slug): Response
    {
        $formOptions = [];

        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        if ($isPatch) {
            $formOptions['method'] = Request::METHOD_PATCH;
            $formOptions['validation_groups'] = false;
        }

        $form = $this->createForm(PlaceMeetingType::class, PlaceMeetingDTO::mock(), $formOptions);
        $form->handleRequest($request);

        if (!$isPatch && $form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/place/meeting.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/places/{slug}/media', name: 'provider_portal_sheet_place_media')]
    public function media(Request $request, string $slug): Response
    {
        $form = $this->createForm(LibraryType::class, LibraryDTO::mock(), [
            'library_configuration' => LibraryConfiguration::forPlace(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/place/media.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/places/{slug}/visibility', name: 'provider_portal_sheet_place_visibility')]
    public function visibility(string $slug): Response
    {
        return $this->render('provider_portal/pages/online/sheet/place/visibility.html.twig', [
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/places/{slug}/invoicing', name: 'provider_portal_sheet_place_invoicing')]
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

    #[Route(path: 'portal/sheets/places/{slug}/template', name: 'provider_portal_sheet_place_template')]
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
            'desktopMenu' => 'Menu:Sheet:Place',
            'mobileMenu' => 'Menu:Sheet:Place:MobileMenu',
            'sheet' => SheetProvider::getSheet($slug),
        ];
    }
}
