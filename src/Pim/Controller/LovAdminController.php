<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Audit\AuditContext;
use App\Enrichment\Entity\AttributeValueTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Service\LovTranslationScheduler;
use App\Pim\Entity\AttributDefinition;
use App\Pim\Entity\ValeurAttribut;
use App\Pim\Form\LovValueAdminType;
use App\Pim\Form\LovSearchType;
use App\Pim\Lov\LovRuntimeCatalog;
use App\Pim\Lov\LovStableIdGenerator;
use App\Shared\Form\ActionType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/listes-de-valeurs', name: 'app_pim_lov_')]
#[IsGranted('ROLE_BP_VALIDATOR')]
final class LovAdminController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, Connection $connection): Response
    {
        $searchForm = $this->createForm(LovSearchType::class);
        $searchForm->handleRequest($request);
        /** @var array{q?: string}|null $search */
        $search = $searchForm->getData();
        $q = trim($search['q'] ?? '');
        $page = max(1, $request->query->getInt('page', 1));
        $params = []; $where = '';
        if ('' !== $q) { $where = ' WHERE a.code LIKE :q OR a.label LIKE :q'; $params['q'] = '%'.$q.'%'; }
        $definitions = $connection->fetchAllAssociative(<<<SQL
            SELECT a.id, a.code, a.label, a.translatable,
                   COUNT(DISTINCT v.id) value_count,
                   MAX(CASE WHEN dt.locale = 'en' THEN dt.translated_label END) translation_en,
                   MAX(CASE WHEN dt.locale = 'es' THEN dt.translated_label END) translation_es,
                   MAX(CASE WHEN dt.locale = 'it' THEN dt.translated_label END) translation_it,
                   MAX(CASE WHEN dt.locale = 'nl' THEN dt.translated_label END) translation_nl,
                   MAX(CASE WHEN dt.locale = 'pt' THEN dt.translated_label END) translation_pt,
                   MAX(CASE WHEN dt.locale = 'de' THEN dt.translated_label END) translation_de
            FROM pim_attribute_definition a
            LEFT JOIN pim_attribute_value v ON v.attribute_id = a.id
            LEFT JOIN pim_attribute_definition_translation dt ON dt.attribute_id = a.id
            {$where}
            GROUP BY a.id, a.code, a.label, a.translatable
            ORDER BY a.code
            LIMIT 50 OFFSET
            SQL.(($page - 1) * 50), $params);

        return $this->render('pim/lov/index.html.twig', ['definitions' => $definitions, 'search_form' => $searchForm, 'query' => $q, 'page' => $page]);
    }

    #[Route('/{code}', name: 'show', methods: ['GET'])]
    public function show(string $code, EntityManagerInterface $entityManager, FormFactoryInterface $forms): Response
    {
        $attribute = $this->attribute($code, $entityManager);
        $values = $entityManager->getRepository(ValeurAttribut::class)->findBy(['attribute' => $attribute], ['position' => 'ASC', 'id' => 'ASC']);
        $translations = [];
        $retryForms = [];
        foreach ($values as $value) {
            foreach ($entityManager->getRepository(AttributeValueTranslation::class)->findBy(['value' => $value]) as $translation) {
                $translations[$value->id()][$translation->locale()->value] = $translation;
            }
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

        return $this->render('pim/lov/show.html.twig', ['attribute' => $attribute, 'values' => $values, 'translations' => $translations, 'retry_forms' => $retryForms]);
    }

    #[Route('/{code}/ajouter', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $code, EntityManagerInterface $entityManager, LovStableIdGenerator $ids, LovTranslationScheduler $scheduler, LovRuntimeCatalog $runtime, AuditContext $audit): Response
    {
        $attribute = $this->attribute($code, $entityManager);
        $form = $this->createForm(LovValueAdminType::class, ['position' => 0, 'active' => true], ['creation' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */ $data = $form->getData();
            $valueCode = strtoupper(trim($data['code']));
            if (null !== $entityManager->getRepository(ValeurAttribut::class)->findOneBy(['attribute' => $attribute, 'code' => $valueCode])) { $form->get('code')->addError(new \Symfony\Component\Form\FormError('Ce code existe déjà.')); }
            else {
                $value = new ValeurAttribut($ids->valueId($attribute->code(), $valueCode), $attribute, $valueCode, (string) $data['label'], (int) $data['position'], (bool) $data['active']);
                $entityManager->persist($value);
                $scheduler->scheduleValue($value);
                $entityManager->flush();
                $actor = $audit->current()['actor'];
                foreach (SupportedLocale::targets() as $locale) {
                    $manual = trim((string) ($data['translation_'.$locale->value] ?? ''));
                    if ('' === $manual) { continue; }
                    $translation = $entityManager->getRepository(AttributeValueTranslation::class)->findOneBy(['value' => $value, 'locale' => $locale]);
                    if ($translation instanceof AttributeValueTranslation) { $translation->applyManual($manual, $actor); }
                }
                $entityManager->flush(); $runtime->reload();
                $this->addFlash('success', 'Valeur LOV ajoutée.');
                return $this->redirectToRoute('app_pim_lov_edit', ['code' => $code, 'id' => $value->id()]);
            }
        }

        return $this->render('pim/lov/form.html.twig', ['form' => $form, 'attribute' => $attribute, 'creation' => true]);
    }

    #[Route('/{code}/{id}/relancer', name: 'retry', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function retry(Request $request, string $code, int $id, EntityManagerInterface $entityManager, FormFactoryInterface $forms, LovTranslationScheduler $scheduler): Response
    {
        $attribute = $this->attribute($code, $entityManager);
        $value = $entityManager->find(ValeurAttribut::class, $id);
        if (!$value instanceof ValeurAttribut || $value->attribute() !== $attribute) { throw $this->createNotFoundException(); }
        $form = $forms->createNamed('retry_'.$value->id(), ActionType::class, null, ['button_label' => 'Relancer Google']);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $scheduled = $scheduler->scheduleValue($value);
            $entityManager->flush();
            $this->addFlash('success', 0 < $scheduled ? 'Traductions LOV planifiées.' : 'Les traductions sont déjà à jour.');
        }

        return $this->redirectToRoute('app_pim_lov_show', ['code' => $code]);
    }

    #[Route('/{code}/{id}/modifier', name: 'edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $code, int $id, EntityManagerInterface $entityManager, LovTranslationScheduler $scheduler, LovRuntimeCatalog $runtime, AuditContext $audit): Response
    {
        $attribute = $this->attribute($code, $entityManager);
        $value = $entityManager->find(ValeurAttribut::class, $id);
        if (!$value instanceof ValeurAttribut || $value->attribute() !== $attribute) { throw $this->createNotFoundException(); }
        $rows = [];
        foreach ($entityManager->getRepository(AttributeValueTranslation::class)->findBy(['value' => $value]) as $translation) { $rows[$translation->locale()->value] = $translation; }
        $data = ['code' => $value->code(), 'label' => $value->label(), 'position' => $value->position(), 'active' => $value->active()];
        foreach (SupportedLocale::targets() as $locale) {
            $translation = $rows[$locale->value] ?? null;
            $data['translation_'.$locale->value] = $translation?->translatedLabel() ?? '';
        }
        $form = $this->createForm(LovValueAdminType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $submitted */ $submitted = $form->getData();
            $labelChanged = trim((string) $submitted['label']) !== $value->label();
            $value->changeLabel((string) $submitted['label']); $value->changePosition((int) $submitted['position']);
            (bool) $submitted['active'] ? $value->activate() : $value->deactivate();
            if ($labelChanged) { $scheduler->scheduleValue($value); }
            $actor = $audit->current()['actor'];
            foreach (SupportedLocale::targets() as $locale) {
                $manual = trim((string) ($submitted['translation_'.$locale->value] ?? ''));
                if ('' === $manual) { continue; }
                $translation = $rows[$locale->value] ?? new AttributeValueTranslation($value, $locale, $value->label());
                if (!isset($rows[$locale->value])) { $entityManager->persist($translation); }
                $translation->applyManual($manual, $actor);
            }
            $entityManager->flush(); $runtime->reload(); $this->addFlash('success', 'Valeur LOV modifiée.');
        }

        return $this->render('pim/lov/form.html.twig', ['form' => $form, 'attribute' => $attribute, 'value' => $value, 'creation' => false]);
    }

    private function attribute(string $code, EntityManagerInterface $entityManager): AttributDefinition
    {
        $attribute = $entityManager->getRepository(AttributDefinition::class)->findOneBy(['code' => $code]);
        if (!$attribute instanceof AttributDefinition) { throw $this->createNotFoundException('LOV introuvable.'); }
        return $attribute;
    }
}
