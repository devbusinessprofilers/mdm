<?php

declare(strict_types=1);

namespace App\Enrichment\Form;

use App\Enrichment\Entity\FicheTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Service\TranslationSource;
use App\Pim\Entity\Fiche;
use App\Shared\Form\ActionType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class TranslationAdminFormFactory
{
    public function __construct(private FormFactoryInterface $forms, private UrlGeneratorInterface $urls)
    {
    }

    /** @return FormInterface<array{fieldPath: string, value: string}> */
    public function correction(Fiche $fiche, TranslationSource $source, SupportedLocale $locale, ?FicheTranslation $translation): FormInterface
    {
        $formName = 'translation_'.substr(hash('sha256', $source->path.'|'.$locale->value), 0, 20);

        return $this->forms->createNamed($formName, TranslationCorrectionType::class, [
            'fieldPath' => $source->path,
            'value' => $translation?->suggestedText() ?? $translation?->translatedText() ?? '',
        ], [
            'action' => $this->urls->generate('app_enrichment_fiche_translation_correct', [
                'id' => $fiche->idString(),
                'locale' => $locale->value,
                'fieldPath' => $source->path,
            ]),
        ]);
    }

    /** @return FormInterface<mixed> */
    public function retry(Fiche $fiche): FormInterface
    {
        return $this->forms->createNamed('action', ActionType::class, null, [
            'action' => $this->urls->generate('app_enrichment_fiche_translation_retry', ['id' => $fiche->idString()]),
            'button_label' => 'Relancer les traductions',
        ]);
    }
}
