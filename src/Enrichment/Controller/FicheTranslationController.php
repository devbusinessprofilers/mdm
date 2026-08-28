<?php

declare(strict_types=1);

namespace App\Enrichment\Controller;

use App\Account\Security\FicheVoter;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Form\TranslationAdminFormFactory;
use App\Enrichment\Repository\FicheTranslationRepository;
use App\Enrichment\Service\FicheTranslationManager;
use App\Enrichment\Service\FicheTranslationSourceExtractor;
use App\Enrichment\Service\FicheTranslationViewBuilder;
use App\Enrichment\Service\TranslationSource;
use App\Pim\Entity\Fiche;
use App\Pim\Repository\FicheRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

#[Route('/referentiel/fiche/{id}/traductions', name: 'app_enrichment_fiche_translation_', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
final class FicheTranslationController extends AbstractController
{
    #[Route('', name: 'show', methods: ['GET'])]
    public function show(string $id, FicheRepository $fiches, FicheTranslationViewBuilder $viewBuilder): Response
    {
        $fiche = $fiches->find(Ulid::fromString($id));
        if (!$fiche instanceof Fiche) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $this->denyAccessUnlessGranted(FicheVoter::VIEW, $fiche);

        return $this->render('enrichment/fiche_translations.html.twig', $viewBuilder->build($fiche, $this->isGranted('ROLE_BP_VALIDATOR')));
    }

    #[Route('/{locale}/corriger', name: 'correct', requirements: ['locale' => 'en|es|it|nl|pt|de'], methods: ['POST'])]
    public function correct(
        Request $request,
        string $id,
        string $locale,
        FicheRepository $fiches,
        FicheTranslationRepository $translations,
        FicheTranslationSourceExtractor $extractor,
        TranslationAdminFormFactory $forms,
        FicheTranslationManager $manager,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $fiche = $fiches->find(Ulid::fromString($id));
        if (!$fiche instanceof Fiche) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $targetLocale = SupportedLocale::from($locale);
        $available = [];
        foreach ($extractor->extract($fiche) as $source) {
            $available[$source->path] = $source;
        }
        $source = $available[$request->query->getString('fieldPath')] ?? null;
        if (!$source instanceof TranslationSource) {
            throw $this->createNotFoundException('Champ traduisible introuvable ou vide.');
        }
        $translation = $translations->findOne($fiche, $source->path, $targetLocale);
        $form = $forms->correction($fiche, $source, $targetLocale, $translation);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{fieldPath: string, value: string} $data */
            $data = $form->getData();
            if ($data['fieldPath'] !== $source->path) {
                throw $this->createNotFoundException('Champ traduisible invalide.');
            }
            $manager->correct($fiche, $source, $targetLocale, $translation, $data['value']);
            $this->addFlash('success', 'Traduction corrigée.');
        }

        return $this->redirectToRoute('app_enrichment_fiche_translation_show', ['id' => $id]);
    }

    #[Route('/relancer', name: 'retry', methods: ['POST'])]
    public function retry(Request $request, string $id, FicheRepository $fiches, TranslationAdminFormFactory $forms, FicheTranslationManager $manager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $fiche = $fiches->find(Ulid::fromString($id));
        if (!$fiche instanceof Fiche) {
            throw $this->createNotFoundException('Fiche introuvable.');
        }
        $form = $forms->retry($fiche);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $scheduled = $manager->retry($fiche);
            if ($scheduled > 0) {
                $this->addFlash('success', sprintf('Traductions planifiées pour %d langue(s).', $scheduled));
            } else {
                $this->addFlash('success', 'Traductions déjà à jour : rien à relancer.');
            }
        }

        return $this->redirectToRoute('app_enrichment_fiche_translation_show', ['id' => $id]);
    }
}
