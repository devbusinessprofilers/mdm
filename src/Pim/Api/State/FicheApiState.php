<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use App\Enrichment\Service\FicheTranslationScheduler;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\TypeFiche;
use App\Pim\Message\IndexFiche;
use App\Pim\Service\FicheDetailResolver;
use App\Pim\Service\FicheMutation;
use App\Shared\Outbox\OutboxPublisherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Socle des opérations de l'API externe, toutes gammes : chargement de la
 * fiche (404 sinon), contrôle de version optimiste par If-Match, puis flush
 * avec réindexation et replanification des traductions.
 */
final readonly class FicheApiState
{
    public function __construct(
        private FicheDetailResolver $details,
        private RequestStack $requests,
        private OutboxPublisherInterface $outbox,
        private FicheTranslationScheduler $translationScheduler,
        private FicheMutation $mutations,
    ) {
    }

    public function entite(TypeFiche $type, string $id): Lieu|Restaurant|Activite|ServiceEvenementiel
    {
        return $this->details->parTypeEtId($type, $id)
            ?? throw new ApiProblemException(Response::HTTP_NOT_FOUND, 'not_found', sprintf('%s introuvable.', $type->libelle()));
    }

    public function lieu(string $id): Lieu
    {
        $entite = $this->entite(TypeFiche::Lieu, $id);

        return $entite instanceof Lieu ? $entite : throw new \LogicException('Fiche d’une autre gamme.');
    }

    public function restaurant(string $id): Restaurant
    {
        $entite = $this->entite(TypeFiche::Restaurant, $id);

        return $entite instanceof Restaurant ? $entite : throw new \LogicException('Fiche d’une autre gamme.');
    }

    public function activite(string $id): Activite
    {
        $entite = $this->entite(TypeFiche::Activite, $id);

        return $entite instanceof Activite ? $entite : throw new \LogicException('Fiche d’une autre gamme.');
    }

    public function service(string $id): ServiceEvenementiel
    {
        $entite = $this->entite(TypeFiche::ServiceEvenementiel, $id);

        return $entite instanceof ServiceEvenementiel ? $entite : throw new \LogicException('Fiche d’une autre gamme.');
    }

    /** L'en-tête If-Match doit porter la version courante de la fiche (428 absent, 409 périmé). */
    public function assertVersion(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): void
    {
        $version = trim((string) $this->requests->getCurrentRequest()?->headers->get('If-Match'), " \t\n\r\x00\v\"");
        if ('' === $version) {
            throw new ApiProblemException(Response::HTTP_PRECONDITION_REQUIRED, 'precondition_required', 'L’en-tête If-Match est obligatoire.');
        }
        if (!ctype_digit($version) || (int) $version !== $entite->fiche()->version()) {
            throw new ApiProblemException(Response::HTTP_CONFLICT, 'version_conflict', 'La fiche a été modifiée depuis sa lecture.', ['currentVersion' => $entite->fiche()->version()]);
        }
    }

    /**
     * Flush avec réindexation et replanification des traductions ; une
     * liaison Lieu ↔ Restaurant modifiée réindexe aussi la fiche liée, sans
     * transition de workflow.
     */
    public function flushAndIndex(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): void
    {
        $this->translationScheduler->schedule($entite->fiche());
        $this->outbox->enqueue(new IndexFiche($entite->fiche()->idString()));
        $this->mutations->enregistrerAvecLiees($entite);
    }
}
