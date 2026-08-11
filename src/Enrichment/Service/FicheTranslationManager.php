<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Audit\AuditContext;
use App\Audit\Entity\AuditChange;
use App\Audit\Entity\AuditRevision;
use App\Etl\Service\MarketplaceSyncScheduler;
use App\Enrichment\Entity\FicheTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Pim\Entity\Fiche;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FicheTranslationManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditContext $auditContext,
        private FicheTranslationScheduler $scheduler,
        private MarketplaceSyncScheduler $marketplaceScheduler,
    ) {}

    public function correct(
        Fiche $fiche,
        TranslationSource $source,
        SupportedLocale $locale,
        ?FicheTranslation $translation,
        string $value,
    ): void {
        if (!$translation instanceof FicheTranslation) {
            $translation = new FicheTranslation($fiche, $source->path, $source->label, $locale, $source->value);
            $this->entityManager->persist($translation);
        } else {
            $translation->schedule($source->label, $source->value, '');
        }

        $old = $translation->translatedText();
        $context = $this->auditContext->current();
        $translation->applyManual($value, $context['actor']);
        $revision = new AuditRevision(
            $fiche->idString(),
            'translation_correction',
            'pim',
            $context['actor'],
            $context['rolesScopes'],
            $context['correlationId'],
        );
        new AuditChange(
            $revision,
            sprintf('translations[%s].%s', $translation->locale()->value, $translation->fieldPath()),
            $old,
            $translation->translatedText(),
        );
        $this->entityManager->persist($revision);
        // Une correction manuelle disponible doit redescendre vers la
        // marketplace comme les traductions automatiques.
        $this->marketplaceScheduler->schedule($fiche);
        $this->entityManager->flush();
    }

    public function retry(Fiche $fiche): void
    {
        $this->scheduler->schedule($fiche);
        $this->entityManager->flush();
    }
}
