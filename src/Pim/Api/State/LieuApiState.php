<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use App\Enrichment\Service\FicheTranslationScheduler;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\LieuRepository;
use App\Pim\Service\FicheMutation;
use App\Shared\Outbox\OutboxPublisherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final readonly class LieuApiState
{
    public function __construct(
        private FicheMutation $mutations,
        private LieuRepository $lieux,
        private RequestStack $requests,
        private OutboxPublisherInterface $outbox,
        private FicheTranslationScheduler $translationScheduler,
    ) {
    }

    public function lieu(string $id): Lieu
    {
        $lieu = $this->lieux->find($id);
        if (!$lieu instanceof Lieu) {
            throw new ApiProblemException(Response::HTTP_NOT_FOUND, 'not_found', 'Lieu introuvable.');
        }

        return $lieu;
    }

    public function assertVersion(Lieu $lieu): void
    {
        $header = trim(
            (string) $this->requests
                ->getCurrentRequest()
                ?->headers->get('If-Match'),
            " \t\n\r\x00\v\"",
        );
        if ('' === $header) {
            throw new ApiProblemException(Response::HTTP_PRECONDITION_REQUIRED, 'precondition_required', 'L’en-tête If-Match est obligatoire.');
        }
        if (
            !ctype_digit($header)
            || (int) $header !== $lieu->fiche()->version()
        ) {
            throw new ApiProblemException(Response::HTTP_CONFLICT, 'version_conflict', 'La fiche a été modifiée depuis sa lecture.', ['currentVersion' => $lieu->fiche()->version()]);
        }
    }

    public function flushAndIndex(Lieu $lieu): void
    {
        $this->translationScheduler->schedule($lieu->fiche());
        $this->outbox->enqueue(new IndexFiche($lieu->fiche()->idString()));
        // Liaison Restaurant modifiée : resynchroniser les fiches liées, sans
        // transition de workflow (flush sous suppression pour ces fiches).
        $this->mutations->enregistrerAvecLiees($lieu);
    }
}
