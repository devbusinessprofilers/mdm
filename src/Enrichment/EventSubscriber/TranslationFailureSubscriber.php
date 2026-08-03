<?php

declare(strict_types=1);

namespace App\Enrichment\EventSubscriber;

use App\Enrichment\Entity\AbstractLovTranslation;
use App\Enrichment\Entity\AttributeDefinitionTranslation;
use App\Enrichment\Entity\AttributeValueTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Message\TranslateLovLabel;
use App\Enrichment\Message\TranslatePublishedFiche;
use App\Enrichment\Repository\FicheTranslationRepository;
use App\Pim\Entity\AttributDefinition;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\ValeurAttribut;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Ulid;

final readonly class TranslationFailureSubscriber
{
    public function __construct(private EntityManagerInterface $entityManager, private FicheTranslationRepository $ficheTranslations) {}

    #[AsEventListener]
    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) { return; }
        $message = $event->getEnvelope()->getMessage();
        $error = $event->getThrowable()->getMessage();
        if ($message instanceof TranslatePublishedFiche) {
            $fiche = $this->entityManager->find(Fiche::class, Ulid::fromString($message->ficheId));
            if ($fiche instanceof Fiche) {
                foreach ($this->ficheTranslations->requested($fiche, SupportedLocale::from($message->locale), $message->requestToken) as $row) { $row->fail($message->requestToken, $error); }
                $this->entityManager->flush();
            }
            return;
        }
        if (!$message instanceof TranslateLovLabel) { return; }
        [$subjectClass, $translationClass, $criterion] = 'definition' === $message->subject
            ? [AttributDefinition::class, AttributeDefinitionTranslation::class, 'attribute']
            : [ValeurAttribut::class, AttributeValueTranslation::class, 'value'];
        $subject = $this->entityManager->find($subjectClass, $message->subjectId);
        if (null === $subject) { return; }
        $row = $this->entityManager->getRepository($translationClass)->findOneBy([$criterion => $subject, 'locale' => SupportedLocale::from($message->locale), 'requestToken' => $message->requestToken]);
        if ($row instanceof AbstractLovTranslation) { $row->fail($message->requestToken, $error); $this->entityManager->flush(); }
    }
}
