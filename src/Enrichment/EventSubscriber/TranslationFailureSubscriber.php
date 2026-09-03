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
use App\Shared\Messenger\AbstractWorkerFailureListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Ulid;

/** Une traduction dont le message a épuisé ses relances passe en erreur, relançable depuis la fiche. */
#[AsEventListener]
final readonly class TranslationFailureSubscriber extends AbstractWorkerFailureListener
{
    public function __construct(
        ManagerRegistry $registry,
        LoggerInterface $logger,
        private FicheTranslationRepository $ficheTranslations,
        private AttributeDefinitionTranslationRepository $definitionTranslations,
        private AttributeValueTranslationRepository $valueTranslations,
    ) {
        parent::__construct($registry, $logger);
    }

    protected function concerne(object $message): bool
    {
        return $message instanceof TranslatePublishedFiche || $message instanceof TranslateLovLabel;
    }

    protected function marquer(EntityManagerInterface $manager, object $message, WorkerMessageFailedEvent $event): void
    {
        $error = $event->getThrowable()->getMessage();
        if ($message instanceof TranslatePublishedFiche) {
            $fiche = Ulid::isValid($message->ficheId) ? $manager->find(Fiche::class, Ulid::fromString($message->ficheId)) : null;
            if (!$fiche instanceof Fiche) {
                return;
            }
            foreach ($this->ficheTranslations->requested($fiche, SupportedLocale::from($message->locale), $message->requestToken) as $row) {
                $row->fail($message->requestToken, $error);
            }

            return;
        }
        /** @var TranslateLovLabel $message */
        $locale = SupportedLocale::from($message->locale);
        if ('definition' === $message->subject) {
            $subject = $manager->find(AttributDefinition::class, $message->subjectId);
            $row = $subject instanceof AttributDefinition
                ? $this->definitionTranslations->findRequested($subject, $locale, $message->requestToken)
                : null;
        } else {
            $subject = $manager->find(ValeurAttribut::class, $message->subjectId);
            $row = $subject instanceof ValeurAttribut
                ? $this->valueTranslations->findRequested($subject, $locale, $message->requestToken)
                : null;
        }
        if ($row instanceof AbstractLovTranslation) {
            $row->fail($message->requestToken, $error);
        }
    }
}
