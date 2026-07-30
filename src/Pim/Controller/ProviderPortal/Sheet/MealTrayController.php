<?php

namespace App\Pim\Controller\ProviderPortal\Sheet;

use App\Pim\Form\ProviderPortal\Invoicing\InvoicingType;
use App\Pim\Form\ProviderPortal\Media\LibraryType;
use App\Pim\Form\ProviderPortal\Sheet\MealTray\MealTrayDescriptionType;
use App\Pim\Form\ProviderPortal\Sheet\MealTray\MealTrayInformationType;
use App\Pim\Form\ProviderPortal\Sheet\MealTray\MealTrayProductType;
use App\Pim\Form\ProviderPortal\Template\TemplateListType;
use App\Pim\Model\ProviderPortal\DTO\Invoicing\InvoicingDTO;
use App\Pim\Model\ProviderPortal\DTO\Media\LibraryDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\MealTray\MealTrayDescriptionDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\MealTray\MealTrayInformationDTO;
use App\Pim\Model\ProviderPortal\DTO\Sheet\MealTray\MealTrayProductDTO;
use App\Pim\Model\ProviderPortal\DTO\Template\TemplateListDTO;
use App\Pim\Model\ProviderPortal\Form\Media\LibraryConfiguration;
use App\Pim\Model\ProviderPortal\Mock\Provider\SheetProvider;
use App\Pim\Model\ProviderPortal\Mock\Sheet\MealTray\TypeChoices;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MealTrayController extends AbstractController
{
    #[Route(path: 'portal/sheets/meal-tray/{slug}/information', name: 'provider_portal_sheet_meal_tray_index')]
    public function index(Request $request, string $slug): Response
    {
        $formOptions = [];

        $isPatch = $request->isMethod(Request::METHOD_PATCH);
        if ($isPatch) {
            $formOptions['method'] = Request::METHOD_PATCH;
            $formOptions['validation_groups'] = false;
        }

        $form = $this->createForm(MealTrayInformationType::class, MealTrayInformationDTO::mock(), $formOptions);
        $form->handleRequest($request);

        if (!$isPatch && $form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/meal_tray/information.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/meal-tray/{slug}/description', name: 'provider_portal_sheet_meal_tray_description')]
    public function description(Request $request, string $slug): Response
    {
        $form = $this->createForm(MealTrayDescriptionType::class, MealTrayDescriptionDTO::mock());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/meal_tray/description.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/meal-tray/{slug}/product/list', name: 'provider_portal_sheet_meal_tray_product_list')]
    public function productList(Request $request, string $slug): Response
    {
        $form = $this->createForm(MealTrayProductType::class, MealTrayProductDTO::mock());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/meal_tray/product_list.html.twig', [
            'productTypes' => array_flip(TypeChoices::getChoices()), // For translation only!
            'products' => [
                MealTrayProductDTO::mock(),
                MealTrayProductDTO::mock(),
                MealTrayProductDTO::mock(),
                MealTrayProductDTO::mock(),
                MealTrayProductDTO::mock(),
            ],
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/meal-tray/{slug}/product/edit/{product}', name: 'provider_portal_sheet_meal_tray_product_edit')]
    public function productEdit(Request $request, string $slug, ?string $product = null): Response
    {
        // @todo: use product entity + manage add/edit...
        $data = (null !== $product) ? MealTrayProductDTO::mock() : new MealTrayProductDTO();

        $form = $this->createForm(MealTrayProductType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dump($form->getData());

            $this->addFlash('info', 'Produit mis à jour');

            return $this->redirectToRoute('provider_portal_sheet_meal_tray_product_list', ['slug' => $slug]);
        }

        return $this->render('provider_portal/pages/online/sheet/meal_tray/product_edit.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/meal-tray/{slug}/product/delete/{product}', name: 'provider_portal_sheet_meal_tray_product_delete', methods: ['POST', 'DELETE'])]
    public function productDelete(string $slug, string $product): Response
    {
        $this->addFlash('info', 'Produit supprimé');

        dump($product);

        return $this->redirectToRoute('provider_portal_sheet_meal_tray_product_list', ['slug' => $slug]);
    }

    #[Route(path: 'portal/sheets/meal-tray/{slug}/csr', name: 'provider_portal_sheet_meal_tray_csr')]
    public function csr(string $slug): Response
    {
        return $this->redirectToRoute('provider_portal_sheet_meal_tray_index', ['slug' => $slug]);
    }

    #[Route(path: 'portal/sheets/meal-tray/{slug}/media', name: 'provider_portal_sheet_meal_tray_media')]
    public function media(Request $request, string $slug): Response
    {
        $form = $this->createForm(LibraryType::class, LibraryDTO::mock(), [
            'library_configuration' => LibraryConfiguration::forPlace(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            dump($form->getData());
        }

        return $this->render('provider_portal/pages/online/sheet/meal_tray/media.html.twig', [
            'form' => $form,
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/meal-tray/{slug}/visibility', name: 'provider_portal_sheet_meal_tray_visibility')]
    public function visibility(string $slug): Response
    {
        return $this->render('provider_portal/pages/online/sheet/meal_tray/visibility.html.twig', [
            ...$this->mockCompletion($slug),
        ]);
    }

    #[Route(path: 'portal/sheets/meal-tray/{slug}/invoicing', name: 'provider_portal_sheet_meal_tray_invoicing')]
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

    #[Route(path: 'portal/sheets/meal-tray/{slug}/template', name: 'provider_portal_sheet_meal_tray_template')]
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
            'desktopMenu' => 'Menu:Sheet:MealTray',
            'mobileMenu' => 'Menu:Sheet:MealTray:MobileMenu',
            'sheet' => SheetProvider::getSheet($slug),
        ];
    }
}
