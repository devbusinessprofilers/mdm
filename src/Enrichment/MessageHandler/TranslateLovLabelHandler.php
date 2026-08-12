<?php

declare(strict_types=1);

namespace App\Enrichment\MessageHandler;

use App\Enrichment\Entity\AbstractLovTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Enum\TranslationStatus;
use App\Enrichment\Message\TranslateLovLabel;
use App\Enrichment\Repository\AttributeDefinitionTranslationRepository;
use App\Enrichment\Repository\AttributeValueTranslationRepository;
use App\Enrichment\Service\TranslationProviderInterface;
use App\Etl\Service\MarketplaceLovSyncScheduler;
use App\Pim\Entity\AttributDefinition;
use App\Pim\Entity\ValeurAttribut;
use App\Pim\Repository\AttributDefinitionRepository;
use App\Pim\Repository\ValeurAttributRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TranslateLovLabelHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AttributDefinitionRepository $attributes,
        private ValeurAttributRepository $values,
        private AttributeDefinitionTranslationRepository $definitionTranslations,
        private AttributeValueTranslationRepository $valueTranslations,
        private TranslationProviderInterface $provider,
        private MarketplaceLovSyncScheduler $marketplace,
    ) {}

    public function __invoke(TranslateLovLabel $message): void
    {
        $locale = SupportedLocale::from($message->locale);
        if ('definition' === $message->subject) {
            $subject = $this->attributes->find($message->subjectId);
            $translation = $subject instanceof AttributDefinition
                ? $this->definitionTranslations->findRequested($subject, $locale, $message->requestToken)
                : null;
        } else {
            $subject = $this->values->find($message->subjectId);
            $translation = $subject instanceof ValeurAttribut
                ? $this->valueTranslations->findRequested($subject, $locale, $message->requestToken)
                : null;
        }
        if (!$translation instanceof AbstractLovTranslation) { return; }
        $result = $this->provider->translate([$translation->sourceLabel()], $locale);
        $translation->applyGoogle($result[0], $message->requestToken);
        // Libellé de valeur traduit : la marketplace reçoit le dictionnaire à
        // jour (les traductions d'attributs ne la concernent pas).
        if ($subject instanceof ValeurAttribut && TranslationStatus::Available === $translation->status()) {
            $this->marketplace->schedule();
        }
        $this->entityManager->flush();
    }
}
