<?php

declare(strict_types=1);

namespace App\Enrichment\EventSubscriber;

use App\Enrichment\Entity\AbstractLovTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Message\TranslateLovLabel;
use App\Enrichment\Message\TranslatePublishedFiche;
use App\Enrichment\Repository\AttributeDefinitionTranslationRepository;
use App\Enrichment\Repository\AttributeValueTranslationRepository;
use App\Enrichment\Repository\FicheTranslationRepository;
use App\Pim\Entity\AttributDefinition;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\ValeurAttribut;
use App\Pim\Repository\AttributDefinitionRepository;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\ValeurAttributRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Ulid;

final readonly class TranslationFailureSubscriber
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FicheRepository $fiches,
        private AttributDefinitionRepository $attributes,
        private ValeurAttributRepository $values,
        private FicheTranslationRepository $ficheTranslations,
        private AttributeDefinitionTranslationRepository $definitionTranslations,
        private AttributeValueTranslationRepository $valueTranslations,
    ) {}

    #[AsEventListener]
    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) { return; }
        $message = $event->getEnvelope()->getMessage();
        $error = $event->getThrowable()->getMessage();
        if ($message instanceof TranslatePublishedFiche) {
            $fiche = $this->fiches->find(Ulid::fromString($message->ficheId));
            if ($fiche instanceof Fiche) {
                foreach ($this->ficheTranslations->requested($fiche, SupportedLocale::from($message->locale), $message->requestToken) as $row) { $row->fail($message->requestToken, $error); }
                $this->entityManager->flush();
            }
            return;
        }
        if (!$message instanceof TranslateLovLabel) { return; }
        $locale = SupportedLocale::from($message->locale);
        if ('definition' === $message->subject) {
            $subject = $this->attributes->find($message->subjectId);
            $row = $subject instanceof AttributDefinition
                ? $this->definitionTranslations->findRequested($subject, $locale, $message->requestToken)
                : null;
        } else {
            $subject = $this->values->find($message->subjectId);
            $row = $subject instanceof ValeurAttribut
                ? $this->valueTranslations->findRequested($subject, $locale, $message->requestToken)
                : null;
        }
        if ($row instanceof AbstractLovTranslation) { $row->fail($message->requestToken, $error); $this->entityManager->flush(); }
    }
}
