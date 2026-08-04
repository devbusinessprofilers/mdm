<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Form\TranslationAdminFormFactory;
use App\Enrichment\Repository\FicheTranslationRepository;
use App\Pim\Entity\Fiche;

final readonly class FicheTranslationViewBuilder
{
    public function __construct(
        private FicheTranslationRepository $translations,
        private FicheTranslationSourceExtractor $extractor,
        private TranslationAdminFormFactory $forms,
    ) {}

    /** @return array<string, mixed> */
    public function build(Fiche $fiche, bool $withCorrectionForms): array
    {
        $sources = [];
        foreach ($this->extractor->availableFields($fiche) as $source) {
            $sources[$source->path] = $source;
        }

        $rowsByField = [];
        foreach ($this->translations->forFiche($fiche) as $row) {
            $rowsByField[$row->fieldPath()][$row->locale()->value] = $row;
            $sources[$row->fieldPath()] ??= new TranslationSource($row->fieldPath(), $row->fieldLabel(), $row->sourceText());
        }

        $correctionForms = [];
        if ($withCorrectionForms) {
            foreach ($sources as $source) {
                if ('' === $source->value) {
                    continue;
                }
                foreach (SupportedLocale::targets() as $locale) {
                    $translation = $rowsByField[$source->path][$locale->value] ?? null;
                    $correctionForms[$source->path][$locale->value] = $this->forms->correction($fiche, $source, $locale, $translation)->createView();
                }
            }
        }

        return [
            'fiche' => $fiche,
            'fields' => array_values($sources),
            'translations' => $rowsByField,
            'locales' => SupportedLocale::targets(),
            'correction_forms' => $correctionForms,
            'retry_form' => $this->forms->retry($fiche)->createView(),
        ];
    }
}
