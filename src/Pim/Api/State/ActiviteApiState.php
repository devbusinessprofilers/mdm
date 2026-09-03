<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use App\Enrichment\Service\FicheTranslationScheduler;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\ActiviteRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final readonly class ActiviteApiState
{
    public function __construct(
        private ActiviteRepository $activities,
        private RequestStack $requests,
        private EntityManagerInterface $em,
        private OutboxPublisherInterface $outbox,
        private FicheTranslationScheduler $translationScheduler,
    ) {
    }

    public function activite(string $id): Activite
    {
        $a = $this->activities->find($id);
        if (!$a instanceof Activite) {
            throw new ApiProblemException(Response::HTTP_NOT_FOUND, 'not_found', 'Activité introuvable.');
        }

        return $a;
    }

    public function assertVersion(Activite $a): void
    {
        $h = trim(
            (string) $this->requests
                ->getCurrentRequest()
                ?->headers->get('If-Match'),
            " \t\n\r\x00\v\"",
        );
        if ('' === $h) {
            throw new ApiProblemException(428, 'precondition_required', 'L’en-tête If-Match est obligatoire.');
        }
        if (!ctype_digit($h) || (int) $h !== $a->fiche()->version()) {
            throw new ApiProblemException(409, 'version_conflict', 'La fiche a été modifiée depuis sa lecture.', ['currentVersion' => $a->fiche()->version()]);
        }
    }

    public function flushAndIndex(Activite $a): void
    {
        $this->translationScheduler->schedule($a->fiche());
        $this->outbox->enqueue(new IndexFiche($a->id()));
        $this->em->flush();
    }
}
