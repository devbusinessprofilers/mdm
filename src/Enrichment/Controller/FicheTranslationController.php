<?php

declare(strict_types=1);

namespace App\Enrichment\Controller;

use App\Account\Entity\User;
use App\Account\Security\FicheVoter;
use App\Audit\AuditContext;
use App\Audit\Entity\AuditChange;
use App\Audit\Entity\AuditRevision;
use App\Enrichment\Entity\FicheTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Form\TranslationCorrectionType;
use App\Enrichment\Repository\FicheTranslationRepository;
use App\Enrichment\Service\FicheTranslationScheduler;
use App\Enrichment\Service\FicheTranslationSourceExtractor;
use App\Enrichment\Service\TranslationSource;
use App\Pim\Entity\Fiche;
use App\Pim\Repository\FicheRepository;
use App\Shared\Form\ActionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

#[Route('/admin/fiches/{id}/traductions', name: 'app_enrichment_fiche_translation_', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class FicheTranslationController extends AbstractController
{
    public function __construct(private readonly FormFactoryInterface $forms) {}

    #[Route('', name: 'show', methods: ['GET'])]
    public function show(string $id, FicheRepository $fiches, FicheTranslationRepository $translations, FicheTranslationSourceExtractor $extractor): Response
    {
        $fiche = $this->fiche($id, $fiches);
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $fiche);
        $rows = $translations->forFiche($fiche);
        $sources = [];
        foreach ($extractor->availableFields($fiche) as $source) { $sources[$source->path] = $source; }
        $rowsByField = [];
        foreach ($rows as $row) {
            $rowsByField[$row->fieldPath()][$row->locale()->value] = $row;
            $sources[$row->fieldPath()] ??= new TranslationSource($row->fieldPath(), $row->fieldLabel(), $row->sourceText());
        }
        $correctionForms = [];
        if ($this->isGranted('ROLE_BP_VALIDATOR')) {
            foreach ($sources as $source) {
                if ('' === $source->value) { continue; }
                foreach (SupportedLocale::targets() as $locale) {
                    $translation = $rowsByField[$source->path][$locale->value] ?? null;
                    $correctionForms[$source->path][$locale->value] = $this->correctionForm($fiche, $source, $locale, $translation)->createView();
                }
            }
        }
        $retryForm = $this->createForm(ActionType::class, null, [
            'action' => $this->generateUrl('app_enrichment_fiche_translation_retry', ['id' => $id]),
            'button_label' => 'Relancer les traductions',
        ]);

        return $this->render('enrichment/fiche_translations.html.twig', [
            'fiche' => $fiche,
            'fields' => array_values($sources),
            'translations' => $rowsByField,
            'locales' => SupportedLocale::targets(),
            'correction_forms' => $correctionForms,
            'retry_form' => $retryForm->createView(),
        ]);
    }

    #[Route('/{locale}/corriger', name: 'correct', requirements: ['locale' => 'en|es|it|nl|pt|de'], methods: ['POST'])]
    public function correct(Request $request, string $id, string $locale, FicheRepository $fiches, FicheTranslationRepository $translations, FicheTranslationSourceExtractor $extractor, EntityManagerInterface $entityManager, AuditContext $auditContext): Response
    {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $fiche = $this->fiche($id, $fiches);
        $targetLocale = SupportedLocale::from($locale);
        $available = [];
        foreach ($extractor->extract($fiche) as $source) { $available[$source->path] = $source; }
        $source = $available[$request->query->getString('fieldPath')] ?? null;
        if (!$source instanceof TranslationSource) { throw $this->createNotFoundException('Champ traduisible introuvable ou vide.'); }
        $translation = $translations->findOne($fiche, $source->path, $targetLocale);
        $form = $this->correctionForm($fiche, $source, $targetLocale, $translation);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{fieldPath: string, value: string} $data */
            $data = $form->getData();
            if ($data['fieldPath'] !== $source->path) { throw $this->createNotFoundException('Champ traduisible invalide.'); }
            if (!$translation instanceof FicheTranslation) {
                $translation = new FicheTranslation($fiche, $source->path, $source->label, $targetLocale, $source->value);
                $entityManager->persist($translation);
            } else {
                $translation->schedule($source->label, $source->value, '');
            }
            $old = $translation->translatedText();
            $context = $auditContext->current();
            $translation->applyManual($data['value'], $context['actor']);
            $revision = new AuditRevision($fiche->idString(), 'translation_correction', 'pim', $context['actor'], $context['rolesScopes'], $context['correlationId']);
            new AuditChange($revision, sprintf('translations[%s].%s', $translation->locale()->value, $translation->fieldPath()), $old, $translation->translatedText());
            $entityManager->persist($revision);
            $entityManager->flush();
            $this->addFlash('success', 'Traduction corrigée.');
        }

        return $this->redirectToRoute('app_enrichment_fiche_translation_show', ['id' => $id]);
    }

    #[Route('/relancer', name: 'retry', methods: ['POST'])]
    public function retry(Request $request, string $id, FicheRepository $fiches, FicheTranslationScheduler $scheduler, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $fiche = $this->fiche($id, $fiches);
        $form = $this->createForm(ActionType::class, null, ['button_label' => 'Relancer les traductions']);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $scheduler->schedule($fiche);
            $entityManager->flush();
            $this->addFlash('success', 'Traductions planifiées.');
        }

        return $this->redirectToRoute('app_enrichment_fiche_translation_show', ['id' => $id]);
    }

    /** @return \Symfony\Component\Form\FormInterface<array{fieldPath: string, value: string}> */
    private function correctionForm(Fiche $fiche, TranslationSource $source, SupportedLocale $locale, ?FicheTranslation $translation): \Symfony\Component\Form\FormInterface
    {
        $formName = 'translation_'.substr(hash('sha256', $source->path.'|'.$locale->value), 0, 20);

        return $this->forms->createNamed($formName, TranslationCorrectionType::class, [
            'fieldPath' => $source->path,
            'value' => $translation?->suggestedText() ?? $translation?->translatedText() ?? '',
        ], ['action' => $this->generateUrl('app_enrichment_fiche_translation_correct', ['id' => $fiche->idString(), 'locale' => $locale->value, 'fieldPath' => $source->path])]);
    }

    private function fiche(string $id, FicheRepository $repository): Fiche
    {
        $fiche = $repository->find(Ulid::fromString($id));
        if (!$fiche instanceof Fiche) { throw $this->createNotFoundException('Fiche introuvable.'); }

        return $fiche;
    }
}
