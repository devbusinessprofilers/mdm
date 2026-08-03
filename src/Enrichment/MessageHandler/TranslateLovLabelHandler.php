<?php

declare(strict_types=1);

namespace App\Enrichment\MessageHandler;

use App\Enrichment\Entity\AbstractLovTranslation;
use App\Enrichment\Entity\AttributeDefinitionTranslation;
use App\Enrichment\Entity\AttributeValueTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Message\TranslateLovLabel;
use App\Enrichment\Service\TranslationProviderInterface;
use App\Pim\Entity\AttributDefinition;
use App\Pim\Entity\ValeurAttribut;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TranslateLovLabelHandler
{
    public function __construct(private EntityManagerInterface $entityManager, private TranslationProviderInterface $provider) {}

    public function __invoke(TranslateLovLabel $message): void
    {
        $locale = SupportedLocale::from($message->locale);
        [$subjectClass, $translationClass, $criterion] = 'definition' === $message->subject
            ? [AttributDefinition::class, AttributeDefinitionTranslation::class, 'attribute']
            : [ValeurAttribut::class, AttributeValueTranslation::class, 'value'];
        $subject = $this->entityManager->find($subjectClass, $message->subjectId);
        if (null === $subject) { return; }
        $translation = $this->entityManager->getRepository($translationClass)->findOneBy([$criterion => $subject, 'locale' => $locale, 'requestToken' => $message->requestToken]);
        if (!$translation instanceof AbstractLovTranslation) { return; }
        $result = $this->provider->translate([$translation->sourceLabel()], $locale);
        $translation->applyGoogle($result[0], $message->requestToken);
        $this->entityManager->flush();
    }
}
