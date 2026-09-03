<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Enrichment\Service\FicheTranslationScheduler;
use App\Etl\Service\PhotoPublicationGuard;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
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
        private PhotoPublicationGuard $photoGuard,
        private LieuObligationsPublication $obligations,
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

    /**
     * @throws \DomainException si la fiche n'est pas validée, ou si un champ
     *                          obligatoire de la bible (gamme Lieu) est vide
     */
    public function publish(Fiche $fiche): void
    {
        $this->refuserSiIncomplete($fiche);
        $fiche->publish();
        $this->translations->schedule($fiche);
        $this->indexAndFlush($fiche);
    }

    /**
     * Champs obligatoires (bible VERSION BP) encore vides : libellés, vides
     * pour une fiche complète ou d'une autre gamme que Lieu.
     *
     * @return list<string>
     */
    public function champsObligatoiresManquants(Fiche $fiche): array
    {
        return $this->obligations->manquantsPourFiche($fiche);
    }

    /**
     * Valide puis publie en un geste (bouton « Valider et publier ») : la
     * publication est retenue si les obligations photos ou les champs
     * obligatoires ne sont pas satisfaits — mêmes gardes que la publication
     * de masse —, la fiche restant validée.
     *
     * @return bool vrai si la fiche a été publiée, faux si elle reste validée
     *
     * @throws \DomainException si la fiche n'est pas en attente de validation
     */
    public function validateAndPublish(Fiche $fiche, string $actor): bool
    {
        $fiche->validate($actor);
        if (!$this->photoGuard->compliant($fiche) || [] !== $this->champsObligatoiresManquants($fiche)) {
            $this->indexAndFlush($fiche);

            return false;
        }
        $fiche->publish();
        $this->translations->schedule($fiche);
        $this->indexAndFlush($fiche);

        return true;
    }

    public function archive(Fiche $fiche, string $actor): void
    {
        $fiche->archive($actor);
        $this->translations->schedule($fiche);
        $this->indexAndFlush($fiche);
    }

    public function unarchive(Fiche $fiche, string $actor): void
    {
        $fiche->unarchive($actor);
        $this->indexAndFlush($fiche);
    }

    /** @throws \DomainException si la fiche n'est pas archivée ou reste incomplète */
    public function republish(Fiche $fiche, string $actor): void
    {
        $this->refuserSiIncomplete($fiche);
        $fiche->republish($actor);
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
        // Liaison Lieu ↔ Restaurant : la fiche liée survit mais son payload
        // change — la détacher et la réindexer, sans transition de workflow.
        $liee = match (true) {
            $subject instanceof Restaurant => $subject->lieu(),
            $subject instanceof Lieu => $subject->restaurant(),
            default => null,
        };
        if ($liee instanceof Lieu) {
            $liee->syncRestaurant(null);
        }
        if ($liee instanceof Restaurant) {
            $liee->syncLieu(null);
        }
        if (null !== $liee) {
            $this->outbox->enqueue(new IndexFiche($liee->fiche()->idString()));
        }
        $this->entityManager->remove($subject);
        Fiche::preserveWorkflowsDuring(
            null === $liee ? [] : [$liee->fiche()],
            fn () => $this->entityManager->flush(),
        );
    }

    public function indexAndFlush(Fiche $fiche): void
    {
        $this->outbox->enqueue(new IndexFiche($fiche->idString()));
        $this->entityManager->flush();
    }

    /** @throws \DomainException */
    private function refuserSiIncomplete(Fiche $fiche): void
    {
        $manquants = $this->champsObligatoiresManquants($fiche);
        if ([] !== $manquants) {
            throw new \DomainException('Publication refusée. '.LieuObligationsPublication::motif($manquants));
        }
    }
}
