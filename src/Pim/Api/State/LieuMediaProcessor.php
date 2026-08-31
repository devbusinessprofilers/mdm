<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dam\Entity\MediaAsset;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\RegenerateMedia;
use App\Dam\Service\ImageVariantRegistry;
use App\Dam\Service\LieuImageUploader;
use App\Pim\Api\Dto\LieuMediaResource;
use App\Pim\Api\Dto\LieuResource;
use App\Pim\Api\Dto\MediaOrderInput;
use App\Pim\Api\Dto\MediaPatchInput;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\ExternalScopeGuard;
use App\Pim\Api\LieuApiMapper;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\RessourceLieuRepository;
use App\Pim\Service\PhotoObligations;
use App\Pim\Service\PhotoPrincipale;
use App\Pim\Service\PhotoUsageCatalog;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/** @implements ProcessorInterface<mixed, LieuMediaResource|LieuResource|void> */
final readonly class LieuMediaProcessor implements ProcessorInterface
{
    private const USAGES = [
        'PHOTO_FACADE',
        'PHOTO_CHAMBRE',
        'PHOTO_RESTAURATION',
        'CONFIG_PHOTO_SALLE',
        'PHOTO_DIVERSE',
        'CONFIG_PLAN_SALLE',
        'LOISIR_EXTERNE_PHOTO',
        'PHOTO',
    ];

    public function __construct(
        private LieuApiState $state,
        private LieuApiMapper $mapper,
        private LieuImageUploader $uploader,
        private PhotoObligations $photoObligations,
        private RessourceLieuRepository $resources,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
        private RequestStack $requests,
        private ExternalScopeGuard $scopes,
        private LoggerInterface $logger,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): LieuMediaResource|LieuResource|null {
        $this->scopes->requireScope(ExternalScopeGuard::MEDIAS_WRITE);
        $lieu = $this->state->lieu((string) ($uriVariables['lieuId'] ?? ''));
        $this->state->assertVersion($lieu);
        if (!($operation instanceof HttpOperation)) {
            throw new \LogicException('Une opération HTTP est requise.');
        }
        $template = $operation->getUriTemplate() ?? '';
        $method = $operation->getMethod();

        return $lieu
            ->fiche()
            ->preserveWorkflowDuring(function () use (
                $data,
                $lieu,
                $method,
                $template,
                $uriVariables,
            ): LieuMediaResource|LieuResource|null {
                if ('POST' === $method && str_ends_with($template, '/medias')) {
                    return $this->upload($lieu);
                }
                if ('PUT' === $method && str_ends_with($template, '/ordre')) {
                    if (!($data instanceof MediaOrderInput)) {
                        throw new ApiProblemException(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'Le tableau ids est obligatoire.');
                    }

                    return $this->order($lieu, $data);
                }
                $resource = $this->resource(
                    $lieu,
                    (string) ($uriVariables['resourceId'] ?? ''),
                );
                if ('PATCH' === $method) {
                    if (!($data instanceof MediaPatchInput)) {
                        throw new ApiProblemException(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'Corps JSON invalide.');
                    }

                    return $this->metadata($lieu, $resource, $data);
                }
                if (
                    'POST' === $method
                    && str_ends_with($template, '/fichier')
                ) {
                    return $this->replace($lieu, $resource);
                }
                if ('DELETE' === $method) {
                    $this->outbox->enqueue(
                        new DeleteMedia($resource->damAssetId()),
                    );
                    $lieu->removeRessource($resource);
                    $this->entityManager->remove($resource);
                    $this->changed($lieu);

                    return null;
                }
                throw new \LogicException('Opération média API inconnue.');
            });
    }

    private function upload(Lieu $lieu): LieuMediaResource
    {
        $maximum = $this->photoObligations->maximum(TypeFiche::Lieu);
        if (count($this->photos($lieu)) >= $maximum) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'media_limit_reached', sprintf('Un lieu ne peut pas contenir plus de %d photos.', $maximum));
        }
        $request = $this->requests->getCurrentRequest();
        if (null === $request) {
            throw new \LogicException('Aucune requête HTTP active.');
        }
        $file = $request->files->get('photo');
        if (!($file instanceof UploadedFile)) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'photo_required', 'Le champ multipart photo est obligatoire.');
        }
        $usage = $request->request->getString('usage', PhotoUsageCatalog::DEFAUT);
        $enTete = $this->usagePrincipaleDeprecie($usage);
        if ($enTete) {
            $usage = PhotoUsageCatalog::DEFAUT;
        }
        $this->assertUsage($usage);
        try {
            $asset = $this->uploader->upload($file, $lieu);
        } catch (\DomainException $exception) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_media', $exception->getMessage());
        }
        $resource = new RessourceLieu();
        $resource->changeDamAssetId($asset->id());
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage($usage);
        $legende = $request->request->get('legende');
        $resource->changeLegende(is_string($legende) ? $legende : null);
        $resource->changePosition(count($this->photos($lieu)));
        $lieu->addRessource($resource);
        if ($enTete) {
            PhotoPrincipale::placerEnTete($lieu->ressources(), $resource);
        }
        try {
            $this->entityManager->persist($asset);
            $this->outbox->enqueue(
                new MediaUploaded(
                    $asset->id(),
                    $asset->originalStorageKey(),
                    $asset->checksum(),
                    ImageVariantRegistry::names(),
                ),
            );
            $this->changed($lieu);
        } catch (\Throwable $exception) {
            $this->cleanup($asset);
            throw $exception;
        }

        return $this->mapper->media($lieu, $resource);
    }

    private function order(Lieu $lieu, MediaOrderInput $input): LieuResource
    {
        $photos = $this->photos($lieu);
        $known = array_map(
            static fn (RessourceLieu $resource): string => $resource->id(),
            $photos,
        );
        if (
            count($input->ids) !== count(array_unique($input->ids))
            || count($input->ids) !== count($known)
            || [] !== array_diff($input->ids, $known)
            || [] !== array_diff($known, $input->ids)
        ) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_media_order', 'L’ordre transmis ne correspond pas aux photos du lieu.');
        }
        $byId = [];
        foreach ($photos as $photo) {
            $byId[$photo->id()] = $photo;
        }
        foreach ($input->ids as $position => $id) {
            $byId[$id]->changePosition($position);
        }
        $this->changed($lieu);

        return $this->mapper->lieu($lieu);
    }

    private function metadata(
        Lieu $lieu,
        RessourceLieu $resource,
        MediaPatchInput $input,
    ): LieuMediaResource {
        $requestData = json_decode(
            (string) $this->requests->getCurrentRequest()?->getContent(),
            true,
        );
        $requestData = is_array($requestData) ? $requestData : [];
        if (array_key_exists('usage', $requestData)) {
            if (!is_string($input->usage)) {
                throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_media_usage', 'La catégorie de photo est invalide.');
            }
            if ($this->usagePrincipaleDeprecie($input->usage)) {
                // La catégorie de la photo est conservée, seule sa place change.
                PhotoPrincipale::placerEnTete($lieu->ressources(), $resource);
            } else {
                $this->assertUsage($input->usage);
                $resource->changeUsage($input->usage);
            }
        }
        if (array_key_exists('legende', $requestData)) {
            $resource->changeLegende($input->legende);
        }
        if (array_key_exists('source', $requestData)) {
            $resource->changeSource($input->source);
        }
        if (array_key_exists('keywords', $requestData)) {
            $resource->changeKeywords($input->keywords);
        }
        if (array_key_exists('rightsExpiresAt', $requestData)) {
            $resource->changeRightsExpiresAt($input->rightsExpiresAt);
        }
        if (array_key_exists('rightsGranted', $requestData)) {
            throw new ApiProblemException(Response::HTTP_FORBIDDEN, 'rights_validation_forbidden', 'La validation des droits est réservée aux validateurs internes du PIM.');
        }
        try {
            $transformationChanged = false;
            if (array_key_exists('crop', $requestData)) {
                $crop = $input->crop;
                $resource->changeCrop(
                    $crop['x'] ?? null,
                    $crop['y'] ?? null,
                    $crop['width'] ?? null,
                    $crop['height'] ?? null,
                );
                $transformationChanged = true;
            }
            if (
                array_key_exists('rotation', $requestData)
                && null !== $input->rotation
            ) {
                $resource->changeRotation($input->rotation);
                $transformationChanged = true;
            }
        } catch (\DomainException $exception) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_transformation', $exception->getMessage());
        }
        if ($transformationChanged) {
            $this->outbox->enqueue(
                new RegenerateMedia($resource->damAssetId()),
            );
        }
        $this->changed($lieu);

        return $this->mapper->media($lieu, $resource);
    }

    private function replace(
        Lieu $lieu,
        RessourceLieu $resource,
    ): LieuMediaResource {
        $file = $this->requests->getCurrentRequest()?->files->get('photo');
        if (!($file instanceof UploadedFile)) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'photo_required', 'Le champ multipart photo est obligatoire.');
        }
        try {
            $asset = $this->uploader->upload($file, $lieu);
        } catch (\DomainException $exception) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_media', $exception->getMessage());
        }
        $oldId = $resource->damAssetId();
        try {
            $this->entityManager->persist($asset);
            $resource->changeDamAssetId($asset->id());
            $this->outbox->enqueue(
                new MediaUploaded(
                    $asset->id(),
                    $asset->originalStorageKey(),
                    $asset->checksum(),
                    ImageVariantRegistry::names(),
                ),
            );
            $this->outbox->enqueue(new DeleteMedia($oldId));
            $this->changed($lieu);
        } catch (\Throwable $exception) {
            $this->cleanup($asset);
            throw $exception;
        }

        return $this->mapper->media($lieu, $resource);
    }

    private function resource(Lieu $lieu, string $id): RessourceLieu
    {
        $resource = $this->resources->find($id);
        if (
            !($resource instanceof RessourceLieu)
            || $resource->lieu() !== $lieu
            || NatureRessource::Photo !== $resource->nature()
        ) {
            throw new ApiProblemException(Response::HTTP_NOT_FOUND, 'not_found', 'Média introuvable.');
        }

        return $resource;
    }

    /** @return list<RessourceLieu> */
    private function photos(Lieu $lieu): array
    {
        return array_values(
            array_filter(
                $lieu->ressources()->toArray(),
                static fn (
                    RessourceLieu $resource,
                ): bool => NatureRessource::Photo === $resource->nature(),
            ),
        );
    }

    private function assertUsage(string $usage): void
    {
        if (!in_array($usage, self::USAGES, true)) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_media_usage', 'La catégorie de photo est invalide.');
        }
    }

    /**
     * Rétrocompat portail : PHOTO_PRINCIPALE n'est plus une catégorie, la
     * principale est la première photo de l'ordre. Un client qui envoie
     * encore cet usage demande en réalité un placement en tête.
     */
    private function usagePrincipaleDeprecie(string $usage): bool
    {
        if ('PHOTO_PRINCIPALE' !== $usage) {
            return false;
        }
        $this->logger->notice('Usage déprécié PHOTO_PRINCIPALE reçu par l’API médias : photo placée en tête.');

        return true;
    }

    private function changed(Lieu $lieu): void
    {
        $lieu->fiche()->markSystemChanged();
        $this->state->flushAndIndex($lieu);
    }

    private function cleanup(MediaAsset $asset): void
    {
        try {
            $this->uploader->delete($asset);
        } catch (\Throwable) {
        }
    }
}
