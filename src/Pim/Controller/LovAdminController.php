<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Audit\AuditContext;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Repository\AttributeValueTranslationRepository;
use App\Pim\Entity\AttributDefinition;
use App\Pim\Entity\ValeurAttribut;
use App\Pim\Form\LovSearchType;
use App\Pim\Form\LovValueAdminType;
use App\Pim\Repository\AttributDefinitionRepository;
use App\Pim\Repository\ValeurAttributRepository;
use App\Pim\Service\LovAdminManager;
use App\Shared\Form\ActionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/listes-de-valeurs', name: 'app_pim_lov_')]
#[IsGranted('ROLE_ADMIN')]
final class LovAdminController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, AttributDefinitionRepository $attributes): Response
    {
        $searchForm = $this->createForm(LovSearchType::class);
        $searchForm->handleRequest($request);
        /** @var array{q?: string}|null $search */
        $search = $searchForm->getData();
        $q = trim($search['q'] ?? '');
        $page = max(1, $request->query->getInt('page', 1));
        $definitions = $attributes->findAdminPage($q, 50, ($page - 1) * 50);

        return $this->render('pim/lov/index.html.twig', ['definitions' => $definitions, 'search_form' => $searchForm, 'query' => $q, 'page' => $page]);
    }

    #[Route('/{code}', name: 'show', methods: ['GET'])]
    public function show(
        string $code,
        AttributDefinitionRepository $attributes,
        ValeurAttributRepository $values,
        AttributeValueTranslationRepository $translations,
        FormFactoryInterface $forms,
    ): Response {
        $attribute = $attributes->findOneByCode($code);
        if (!$attribute instanceof AttributDefinition) {
            throw $this->createNotFoundException('LOV introuvable.');
        }
        $attributeValues = $values->findOrderedByAttribute($attribute);
        $translationRows = $translations->indexedForValues($attributeValues);
        $retryForms = [];
        foreach ($attributeValues as $value) {
            $retryForms[$value->id()] = $forms->createNamed(
                'retry_'.$value->id(),
                ActionType::class,
                null,
                [
                    'button_label' => 'Relancer Google',
                    'action' => $this->generateUrl('app_pim_lov_retry', ['code' => $code, 'id' => $value->id()]),
                ],
            )->createView();
        }

        return $this->render('pim/lov/show.html.twig', ['attribute' => $attribute, 'values' => $attributeValues, 'translations' => $translationRows, 'retry_forms' => $retryForms]);
    }

    #[Route('/{code}/ajouter', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        string $code,
        AttributDefinitionRepository $attributes,
        LovAdminManager $manager,
        AuditContext $audit,
    ): Response {
        $attribute = $attributes->findOneByCode($code);
        if (!$attribute instanceof AttributDefinition) {
            throw $this->createNotFoundException('LOV introuvable.');
        }
        $form = $this->createForm(LovValueAdminType::class, ['position' => 0, 'active' => true], ['creation' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */ $data = $form->getData();
            try {
                $value = $manager->create($attribute, $data, $audit->current()['actor']);
                $this->addFlash('success', 'Valeur LOV ajoutée.');

                return $this->redirectToRoute('app_pim_lov_edit', ['code' => $code, 'id' => $value->id()]);
            } catch (\DomainException $exception) {
                $form->get('code')->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('pim/lov/form.html.twig', ['form' => $form, 'attribute' => $attribute, 'creation' => true]);
    }

    #[Route('/{code}/{id}/relancer', name: 'retry', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function retry(
        Request $request,
        string $code,
        int $id,
        AttributDefinitionRepository $attributes,
        ValeurAttributRepository $values,
        FormFactoryInterface $forms,
        LovAdminManager $manager,
    ): Response {
        $attribute = $attributes->findOneByCode($code);
        if (!$attribute instanceof AttributDefinition) {
            throw $this->createNotFoundException('LOV introuvable.');
        }
        $value = $values->findOneForAttribute($attribute, $id);
        if (!$value instanceof ValeurAttribut) {
            throw $this->createNotFoundException();
        }
        $form = $forms->createNamed('retry_'.$value->id(), ActionType::class, null, ['button_label' => 'Relancer Google']);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $scheduled = $manager->retry($value);
            $this->addFlash('success', 0 < $scheduled ? 'Traductions LOV planifiées.' : 'Les traductions sont déjà à jour.');
        }

        return $this->redirectToRoute('app_pim_lov_show', ['code' => $code]);
    }

    #[Route('/{code}/{id}/modifier', name: 'edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        string $code,
        int $id,
        AttributDefinitionRepository $attributes,
        ValeurAttributRepository $values,
        AttributeValueTranslationRepository $translations,
        LovAdminManager $manager,
        AuditContext $audit,
    ): Response {
        $attribute = $attributes->findOneByCode($code);
        if (!$attribute instanceof AttributDefinition) {
            throw $this->createNotFoundException('LOV introuvable.');
        }
        $value = $values->findOneForAttribute($attribute, $id);
        if (!$value instanceof ValeurAttribut) {
            throw $this->createNotFoundException();
        }
        $rows = $translations->indexedForValues([$value])[$value->id()] ?? [];
        $data = ['code' => $value->code(), 'label' => $value->label(), 'position' => $value->position(), 'active' => $value->active()];
        foreach (SupportedLocale::targets() as $locale) {
            $translation = $rows[$locale->value] ?? null;
            $data['translation_'.$locale->value] = $translation?->translatedLabel() ?? '';
        }
        $form = $this->createForm(LovValueAdminType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $submitted */ $submitted = $form->getData();
            $manager->update($value, $submitted, $audit->current()['actor']);
            $this->addFlash('success', 'Valeur LOV modifiée.');
        }

        return $this->render('pim/lov/form.html.twig', ['form' => $form, 'attribute' => $attribute, 'value' => $value, 'creation' => false]);
    }
}
