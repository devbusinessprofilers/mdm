<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dam\Entity\MediaAsset;
use App\Dam\Enum\DocumentUsage;
use App\Dam\Enum\MediaKind;
use App\Dam\Enum\MediaStatus;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\PublishDocument;
use App\Dam\Message\UnpublishDocument;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\DocumentUploadException;
use App\Dam\Service\FicheDocumentUploader;
use App\Dam\Service\LieuDocumentPresenter;
use App\Pim\Api\Dto\DocumentPatchInput;
use App\Pim\Api\Dto\DocumentPublicationInput;
use App\Pim\Api\Dto\FicheDocumentResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\ExternalDocumentAccess;
use App\Pim\Api\ProfilApiGamme;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documents des fiches dans l'API externe, toutes gammes : dépôt, métadonnées
 * (usage et salle quand la gamme les permet), remplacement, publication et
 * suppression, sous les scopes de ExternalDocumentAccess. Chaque écriture
 * exige If-Match et laisse le workflow de la fiche intact.
 *
 * @implements ProcessorInterface<mixed, FicheDocumentResource|void>
 */
final readonly class FicheDocumentProcessor implements ProcessorInterface
{
    public function __construct(
        private FicheApiState $state,
        private RessourceLieuRepository $resources,
        private MediaAssetRepository $assets,
        private FicheDocumentUploader $uploader,
        private LieuDocumentPresenter $presenter,
        private ExternalDocumentAccess $access,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private RequestStack $requests,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?FicheDocumentResource
    {
        $profil = ProfilApiGamme::depuisUriVariables($uriVariables);
        $entite = $this->state->entite($profil->type, $profil->id($uriVariables));
        $this->state->assertVersion($entite);
        if (!$operation instanceof HttpOperation) {
            throw new \LogicException('Opération HTTP requise.');
        }
        $method = $operation->getMethod();
        $template = $operation->getUriTemplate() ?? '';

        return $entite->fiche()->preserveWorkflowDuring(function () use ($data, $profil, $entite, $method, $template, $uriVariables): ?FicheDocumentResource {
            if ('POST' === $method && str_ends_with($template, '/documents')) {
                return $this->create($profil, $entite);
            }
            $document = $this->document($profil, $entite, (string) ($uriVariables['documentId'] ?? ''));
            if ('PATCH' === $method) {
                if (!$data instanceof DocumentPatchInput) {
                    throw new ApiProblemException(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'Corps JSON invalide.');
                }

                return $this->metadata($profil, $entite, $document, $data);
            }
            if ('POST' === $method && str_ends_with($template, '/fichier')) {
                return $this->replace($entite, $document);
            }
            if ('POST' === $method && str_ends_with($template, '/publication')) {
                if (!$data instanceof DocumentPublicationInput) {
                    throw new ApiProblemException(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'Corps JSON invalide.');
                }

                return $this->publication($entite, $document, $data->published);
            }
            if ('DELETE' === $method) {
                $this->access->requireWrite($document);
                $this->unpublish($document);
                $this->outbox->enqueue(new DeleteMedia($document->damAssetId()));
                $entite->removeRessource($document);
                $this->entityManager->remove($document);
                $this->changed($entite);

                return null;
            }
            throw new \LogicException('Opération documentaire inconnue.');
        });
    }

    private function create(ProfilApiGamme $profil, Lieu|Restaurant|Activite|ServiceEvenementiel $entite): FicheDocumentResource
    {
        $request = $this->request();
        // Les gammes à usage unique (supports commerciaux) ignorent le champ usage.
        $usage = $profil->usageDocumentUnique() ?? DocumentUsage::tryFrom($request->request->getString('usage'));
        if (null === $usage || !$profil->documentAutorise($usage)) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_document_usage', 'Usage documentaire invalide pour cette gamme.');
        }
        $this->access->requireCreate($usage->access());
        $file = $request->files->get('document');
        if (!$file instanceof UploadedFile) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'document_required', 'Le champ multipart document est obligatoire.');
        }
        $salle = $this->salle($profil, $entite, $request->request->getString('salleId'), $usage);
        $this->assertMaximum($entite, $usage, $salle);
        if ($request->request->getBoolean('rightsGranted')) {
            throw new ApiProblemException(Response::HTTP_FORBIDDEN, 'rights_validation_forbidden', 'La validation des droits est réservée aux validateurs internes du PIM.');
        }
        $asset = $this->upload($file, $entite, $usage);
        try {
            $document = new RessourceLieu();
            $document->configureDocument($usage);
            $document->changeDamAssetId($asset->id());
            $document->rattacherSalle($salle);
            $document->changeLegende($request->request->getString('title'));
            $document->changeSource($request->request->getString('source'));
            $entite->addRessource($document);
            $this->entityManager->persist($asset);
            $this->changed($entite);
        } catch (\Throwable $exception) {
            $this->cleanup($asset);
            throw $exception;
        }

        return $this->presenter->resource($document);
    }

    private function metadata(ProfilApiGamme $profil, Lieu|Restaurant|Activite|ServiceEvenementiel $entite, RessourceLieu $document, DocumentPatchInput $input): FicheDocumentResource
    {
        $this->access->requireWrite($document);
        $rightsWereGranted = $document->rightsGranted();
        $payload = json_decode((string) $this->request()->getContent(), true);
        $payload = is_array($payload) ? $payload : [];
        if (\array_key_exists('rightsGranted', $payload)) {
            throw new ApiProblemException(Response::HTTP_FORBIDDEN, 'rights_validation_forbidden', 'La validation des droits est réservée aux validateurs internes du PIM.');
        }
        if (\array_key_exists('usage', $payload)) {
            $usage = DocumentUsage::tryFrom((string) $input->usage);
            if (null === $usage || !$profil->documentAutorise($usage)) {
                throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_document_usage', 'Usage documentaire invalide pour cette gamme.');
            }
            $this->access->requireCreate($usage->access());
            // Sans salleId, le nouvel usage repart sans salle (un plan exige la sienne).
            $salle = $this->salle($profil, $entite, (string) $input->salleId, $usage);
            $this->assertMaximum($entite, $usage, $salle, $document);
            if ($usage !== $document->documentUsage()) {
                $this->unpublish($document);
            }
            $document->configureDocument($usage);
            $document->rattacherSalle($salle);
        } elseif (\array_key_exists('salleId', $payload)) {
            $usage = $document->documentUsage() ?? throw new \LogicException('Document sans usage.');
            $document->rattacherSalle($this->salle($profil, $entite, (string) $input->salleId, $usage));
        }
        if (\array_key_exists('title', $payload)) {
            $document->changeLegende($input->title);
        }
        if (\array_key_exists('source', $payload)) {
            $document->changeSource($input->source);
        }
        if (\array_key_exists('keywords', $payload)) {
            $document->changeKeywords($input->keywords);
        }
        if (\array_key_exists('rightsExpiresAt', $payload)) {
            $document->changeRightsExpiresAt($input->rightsExpiresAt);
        }
        if ($rightsWereGranted && !$document->rightsGranted()) {
            $this->unpublish($document);
        }
        $this->changed($entite);

        return $this->presenter->resource($document);
    }

    private function replace(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, RessourceLieu $document): FicheDocumentResource
    {
        $this->access->requireWrite($document);
        $file = $this->request()->files->get('document');
        if (!$file instanceof UploadedFile) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'document_required', 'Le champ multipart document est obligatoire.');
        }
        $usage = $document->documentUsage() ?? throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_document_usage', 'Usage documentaire invalide.');
        $asset = $this->upload($file, $entite, $usage);
        $old = $document->damAssetId();
        try {
            $this->unpublish($document);
            $this->entityManager->persist($asset);
            $document->changeDamAssetId($asset->id());
            $this->outbox->enqueue(new DeleteMedia($old));
            $this->changed($entite);
        } catch (\Throwable $exception) {
            $this->cleanup($asset);
            throw $exception;
        }

        return $this->presenter->resource($document);
    }

    private function publication(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, RessourceLieu $document, bool $published): FicheDocumentResource
    {
        $this->access->requirePublish();
        if ($published) {
            $asset = $this->assets->find($document->damAssetId());
            if (!$asset instanceof MediaAsset || MediaKind::Document !== $asset->kind() || MediaStatus::Processed !== $asset->status()) {
                throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_document', 'Le fichier DAM est absent ou invalide.');
            }
            try {
                $document->requestPublication();
            } catch (\DomainException $exception) {
                throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'publication_refused', $exception->getMessage());
            }
            $this->outbox->enqueue(new PublishDocument($document->id()));
        } else {
            $this->unpublish($document);
        }
        $this->changed($entite);

        return $this->presenter->resource($document);
    }

    private function document(ProfilApiGamme $profil, Lieu|Restaurant|Activite|ServiceEvenementiel $entite, string $id): RessourceLieu
    {
        $document = $this->resources->find($id);
        if (!$document instanceof RessourceLieu || !FicheDocumentProvider::estDocument($profil, $entite->fiche(), $document)) {
            throw new ApiProblemException(Response::HTTP_NOT_FOUND, 'not_found', 'Document introuvable.');
        }

        return $document;
    }

    /**
     * Salle demandée par `salleId` : elle doit appartenir à la fiche ; un
     * usage qui exige une salle (plan de salle) ne peut pas s'en passer. Les
     * gammes sans salles refusent tout rattachement.
     */
    private function salle(ProfilApiGamme $profil, Lieu|Restaurant|Activite|ServiceEvenementiel $entite, string $id, DocumentUsage $usage): Salle|RestaurantSalle|null
    {
        if ('' === trim($id)) {
            if ($usage->requiresRoom()) {
                throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'room_required', 'Un plan de salle doit être rattaché à une salle.');
            }

            return null;
        }
        if ($profil->avecSalles() && ($entite instanceof Lieu || $entite instanceof Restaurant)) {
            foreach ($entite->salles() as $salle) {
                if ($salle->id() === $id) {
                    return $salle;
                }
            }
        }
        throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'foreign_room', 'La salle doit appartenir à la fiche.');
    }

    /** Plafond de documents par usage (et par salle pour les plans). */
    private function assertMaximum(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, DocumentUsage $usage, Salle|RestaurantSalle|null $salle, ?RessourceLieu $current = null): void
    {
        $maximum = $usage->maximumCount();
        if (null === $maximum) {
            return;
        }
        $count = 0;
        foreach ($entite->fiche()->resources() as $resource) {
            if ($resource !== $current && $resource->documentUsage() === $usage && (!$usage->requiresRoom() || $resource->salleRattachee() === $salle)) {
                ++$count;
            }
        }
        if ($count >= $maximum) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'document_limit_reached', 'Le nombre maximal de documents pour cet usage est atteint.');
        }
    }

    private function upload(UploadedFile $file, Lieu|Restaurant|Activite|ServiceEvenementiel $entite, DocumentUsage $usage): MediaAsset
    {
        try {
            return $this->uploader->upload($file, $entite->fiche(), $usage);
        } catch (DocumentUploadException $exception) {
            throw new ApiProblemException($exception->httpStatus, 'invalid_document_file', $exception->getMessage());
        }
    }

    private function unpublish(RessourceLieu $document): void
    {
        $key = $document->requestUnpublication();
        if (null !== $key) {
            $this->outbox->enqueue(new UnpublishDocument($document->id(), $key));
        }
    }

    private function changed(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): void
    {
        $entite->fiche()->markSystemChanged();
        $this->state->flushAndIndex($entite);
    }

    private function cleanup(MediaAsset $asset): void
    {
        try {
            $this->uploader->delete($asset);
        } catch (\Throwable) {
        }
    }

    private function request(): Request
    {
        return $this->requests->getCurrentRequest() ?? throw new \LogicException('Aucune requête HTTP active.');
    }
}
