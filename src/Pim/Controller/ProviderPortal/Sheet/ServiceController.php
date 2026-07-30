<?php

namespace App\Pim\Controller\ProviderPortal\Sheet;

use App\Pim\Form\ProviderPortal\Invoicing\InvoicingType;
use App\Pim\Form\ProviderPortal\Media\LibraryType;
use App\Pim\Form\ProviderPortal\Sheet\Service\ServiceInformationType;
use App\Pim\Form\ProviderPortal\Sheet\Service\ServiceLocalisationType;
use App\Pim\Form\ProviderPortal\Sheet\Service\ServicePriceType;
use App\Pim\Form\ProviderPortal\Sheet\Service\ServiceTypologyType;
use App\Pim\Form\ProviderPortal\Template\TemplateListType;
use App\Pim\Model\ProviderPortal\DTO\Invoicing\InvoicingDTO;
use App\Pim\Model\ProviderPortal\DTO\Media\LibraryDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Service\ServiceInformationDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Service\ServiceLocalisationDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Service\ServicePriceDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\Service\ServiceTypologyDTO;
use App\Pim\Model\ProviderPortal\DTO\Template\TemplateListDTO;
use App\Pim\Model\ProviderPortal\Form\Media\LibraryConfiguration;
use App\Pim\Model\ProviderPortal\Mock\Provider\SheetProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ServiceController extends AbstractController
{
    #[Route(path: 'portal/sheets/services/{slug}/information', name: 'provider_portal_sheet_service_index')]
    public function index(Request $request, string $slug): Response
    {
        $form = $this->createForm(ServiceInformationType::class, ServiceInformationDTO::mock());
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/online/sheet/service/information.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/services/{slug}/localisation', name: 'provider_portal_sheet_service_localisation')]
    public function localisation(Request $request, string $slug): Response
    {
        $formOptions = [];

        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        if ($isPatch) {
            $formOptions['method'] = Request::METHOD_PATCH;
            $formOptions['validation_groups'] = false;
        }

        $form = $this->createForm(ServiceLocalisationType::class, ServiceLocalisationDTO::mock(), $formOptions);
        $form->handleRequest($request);

        if (!$isPatch && $form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/service/localisation.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/services/{slug}/detail', name: 'provider_portal_sheet_service_detail')]
    public function detail(Request $request, string $slug): Response
    {
        $form = $this->createForm(ServiceTypologyType::class, ServiceTypologyDTO::mock());
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/online/sheet/service/detail.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/services/{slug}/price', name: 'provider_portal_sheet_service_price')]
    public function price(Request $request, string $slug): Response
    {
        $form = $this->createForm(ServicePriceType::class, ServicePriceDTO::mock());
        $form->handleRequest($request);

        return $this->render('provider_portal/pages/online/sheet/service/price.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/services/{slug}/media', name: 'provider_portal_sheet_service_media')]
    public function media(Request $request, string $slug): Response
    {
        $form = $this->createForm(LibraryType::class, LibraryDTO::mock(), [
            'library_configuration' => LibraryConfiguration::forPlace(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/service/media.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/services/{slug}/visibility', name: 'provider_portal_sheet_service_visibility')]
    public function visibility(string $slug): Response
    {
        return $this->render('provider_portal/pages/online/sheet/service/visibility.html.twig', [
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/services/{slug}/invoicing', name: 'provider_portal_sheet_service_invoicing')]
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

    #[Route(path: 'portal/sheets/services/{slug}/template', name: 'provider_portal_sheet_service_template')]
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
            'desktopMenu' => 'Menu:Sheet:Service',
            'mobileMenu' => 'Menu:Sheet:Service:MobileMenu',
            'sheet' => SheetProvider::getSheet($slug),
        ];
    }
}
