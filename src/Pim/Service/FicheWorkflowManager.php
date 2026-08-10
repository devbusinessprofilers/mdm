<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Enrichment\Service\FicheTranslationScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Message\IndexFiche;
use App\Pim\Validation\ValidationGroups;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class FicheWorkflowManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private FicheTranslationScheduler $translations,
        private ValidatorInterface $validator,
    ) {}

    public function submit(object $subject, Fiche $fiche, string $actor): ConstraintViolationListInterface
    {
        $violations = $this->validator->validate($subject, null, [ValidationGroups::DRAFT, ValidationGroups::SUBMISSION]);
        if (0 === count($violations)) {
            $fiche->submitForValidation($actor);
            $this->indexAndFlush($fiche);
        }

        return $violations;
    }

    public function validate(Fiche $fiche, string $actor): void
    {
        $fiche->validate($actor);
        $this->indexAndFlush($fiche);
    }

    public function publish(Fiche $fiche): void
    {
        $fiche->publish();
        $this->translations->schedule($fiche);
        $this->indexAndFlush($fiche);
    }

    public function archive(Fiche $fiche, string $actor): void
    {
        $fiche->archive($actor);
        $this->translations->schedule($fiche);
        $this->indexAndFlush($fiche);
    }

    public function reject(Fiche $fiche, string $actor, string $reason): void
    {
        $fiche->rejectValidation($actor, $reason);
        $this->indexAndFlush($fiche);
    }

    public function delete(object $subject): void
    {
        $this->entityManager->remove($subject);
        $this->entityManager->flush();
    }

    public function indexAndFlush(Fiche $fiche): void
    {
        $this->outbox->enqueue(new IndexFiche($fiche->idString()));
        $this->entityManager->flush();
    }
}
