<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use App\Pim\Api\Exception\ApiProblemException;
use App\Enrichment\Service\FicheTranslationScheduler;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\ServiceEvenementielRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final readonly class ServiceEvenementielApiState
{
    public function __construct(
        private ServiceEvenementielRepository $services,
        private RequestStack $requests,
        private EntityManagerInterface $em,
        private OutboxPublisherInterface $outbox,
        private FicheTranslationScheduler $translationScheduler,
    ) {}

    public function service(string $id): ServiceEvenementiel
    {
        $a = $this->services->find($id);
        if (!($a instanceof ServiceEvenementiel)) {
            throw new ApiProblemException(
                Response::HTTP_NOT_FOUND,
                "not_found",
                "Service introuvable.",
            );
        }

        return $a;
    }

    public function assertVersion(ServiceEvenementiel $a): void
    {
        $h = trim(
            (string) $this->requests
                ->getCurrentRequest()
                ?->headers->get("If-Match"),
            " \t\n\r\x00\v\"",
        );
        if ("" === $h) {
            throw new ApiProblemException(
                428,
                "precondition_required",
                "L’en-tête If-Match est obligatoire.",
            );
        }
        if (!ctype_digit($h) || (int) $h !== $a->fiche()->version()) {
            throw new ApiProblemException(
                409,
                "version_conflict",
                "La fiche a été modifiée depuis sa lecture.",
                ["currentVersion" => $a->fiche()->version()],
            );
        }
    }

    public function flushAndIndex(ServiceEvenementiel $a): void
    {
        $this->translationScheduler->schedule($a->fiche());
        $this->outbox->enqueue(new IndexFiche($a->id()));
        $this->em->flush();
    }
}
