<?php

declare(strict_types=1);

namespace App\Pim\Api\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dam\Entity\MediaAsset;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\RegenerateMedia;
use App\Dam\Service\FicheImageUploader;
use App\Dam\Service\ImageVariantRegistry;
use App\Pim\Api\Dto\LieuMediaResource;
use App\Pim\Api\Dto\MediaOrderInput;
use App\Pim\Api\Dto\MediaPatchInput;
use App\Pim\Api\Dto\RestaurantResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\ExternalScopeGuard;
use App\Pim\Api\RestaurantApiMapper;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantSalle;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/** @implements ProcessorInterface<mixed, LieuMediaResource|RestaurantResource|void> */
final readonly class RestaurantMediaProcessor implements ProcessorInterface
{
    public function __construct(
        private RestaurantApiState $state,
        private RestaurantApiMapper $mapper,
        private FicheImageUploader $uploader,
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
    ): LieuMediaResource|RestaurantResource|null {
        $this->scopes->requireScope(ExternalScopeGuard::MEDIAS_WRITE);
        $restaurant = $this->state->restaurant(
            (string) ($uriVariables['restaurantId'] ?? ''),
        );
        $this->state->assertVersion($restaurant);
        if (!$operation instanceof HttpOperation) {
            throw new \LogicException('Une opération HTTP est requise.');
        }

        $method = $operation->getMethod();
        $template = $operation->getUriTemplate() ?? '';

        return $restaurant->fiche()->preserveWorkflowDuring(
            function () use (
                $data,
                $restaurant,
                $method,
                $template,
                $uriVariables,
            ): LieuMediaResource|RestaurantResource|null {
                if ('POST' === $method && str_ends_with($template, '/medias')) {
                    return $this->upload($restaurant);
                }
                if ('PUT' === $method && str_ends_with($template, '/ordre')) {
                    if (!$data instanceof MediaOrderInput) {
                        throw new ApiProblemException(
                            400,
                            'invalid_payload',
                            'Le tableau ids est obligatoire.',
                        );
                    }

                    return $this->order($restaurant, $data);
                }

                $resource = $this->resource(
                    $restaurant,
                    (string) ($uriVariables['resourceId'] ?? ''),
                );
                if ('PATCH' === $method) {
                    if (!$data instanceof MediaPatchInput) {
                        throw new ApiProblemException(
                            400,
                            'invalid_payload',
                            'Corps JSON invalide.',
                        );
                    }

                    return $this->metadata($restaurant, $resource, $data);
                }
                if ('POST' === $method && str_ends_with($template, '/fichier')) {
                    return $this->replace($restaurant, $resource);
                }
                if ('DELETE' === $method) {
                    $this->outbox->enqueue(
                        new DeleteMedia($resource->damAssetId()),
                    );
                    $restaurant->removeRessource($resource);
                    $this->entityManager->remove($resource);
                    $this->changed($restaurant);

                    return null;
                }

                throw new \LogicException('Opération média inconnue.');
            },
        );
    }

    private function upload(Restaurant $restaurant): LieuMediaResource
    {
        $maximum = $this->photoObligations->maximum(TypeFiche::Restaurant);
        if (count($this->photos($restaurant)) >= $maximum) {
            throw new ApiProblemException(
                422,
                'media_limit_reached',
                sprintf('Un Restaurant ne peut pas contenir plus de %d photos.', $maximum),
            );
        }

        $request = $this->request();
        $file = $request->files->get('photo');
        if (!$file instanceof UploadedFile) {
            throw new ApiProblemException(
                422,
                'photo_required',
                'Le champ multipart photo est obligatoire.',
            );
        }

        $usage = $request->request->getString('usage', PhotoUsageCatalog::DEFAUT);
        $enTete = $this->usagePrincipaleDeprecie($usage);
        if ($enTete) {
            $usage = PhotoUsageCatalog::DEFAUT;
        }
        $this->assertUsage($usage);
        $room = $this->room(
            $restaurant,
            $request->request->getString('salleId'),
        );
        if ('CONFIG_SALLE_PHOTO' === $usage && null === $room) {
            throw new ApiProblemException(
                422,
                'room_required',
                'Une photo de salle doit être rattachée à une salle.',
            );
        }

        try {
            $asset = $this->uploader->upload($file, $restaurant->fiche());
        } catch (\DomainException $exception) {
            throw new ApiProblemException(
                422,
                'invalid_media',
                $exception->getMessage(),
            );
        }

        $resource = new RessourceLieu();
        $resource->changeDamAssetId($asset->id());
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage($usage);
        $resource->changeRestaurantSalle($room);
        $legend = $request->request->get('legende');
        $resource->changeLegende(is_string($legend) ? $legend : null);
        $resource->changePosition(count($this->photos($restaurant)));

        try {
            $this->entityManager->persist($asset);
            $restaurant->addRessource($resource);
            if ($enTete) {
                PhotoPrincipale::placerEnTete($restaurant->ressources(), $resource);
            }
            $this->outbox->enqueue(
                new MediaUploaded(
                    $asset->id(),
                    $asset->originalStorageKey(),
                    $asset->checksum(),
                    ImageVariantRegistry::names(),
                ),
            );
            $this->changed($restaurant);
        } catch (\Throwable $exception) {
            $this->cleanup($asset);
            throw $exception;
        }

        return $this->mapper->media($restaurant, $resource);
    }

    private function order(
        Restaurant $restaurant,
        MediaOrderInput $input,
    ): RestaurantResource {
        $photos = $this->photos($restaurant);
        $known = array_map(
            static fn (RessourceLieu $resource): string => $resource->id(),
            $photos,
        );
        if (
            count($input->ids) !== count($known)
            || [] !== array_diff($input->ids, $known)
            || count($input->ids) !== count(array_unique($input->ids))
        ) {
            throw new ApiProblemException(
                422,
                'invalid_media_order',
                'La liste doit contenir chaque média une seule fois.',
            );
        }

        $byId = [];
        foreach ($photos as $photo) {
            $byId[$photo->id()] = $photo;
        }
        foreach ($input->ids as $position => $id) {
            $byId[$id]->changePosition($position);
        }
        $this->changed($restaurant);

        return $this->mapper->restaurant($restaurant);
    }

    private function metadata(
        Restaurant $restaurant,
        RessourceLieu $resource,
        MediaPatchInput $input,
    ): LieuMediaResource {
        $payload = json_decode((string) $this->request()->getContent(), true);
        $payload = is_array($payload) ? $payload : [];

        if (array_key_exists('usage', $payload)) {
            if (!is_string($input->usage)) {
                throw new ApiProblemException(
                    422,
                    'invalid_media_usage',
                    'La catégorie est invalide.',
                );
            }
            if ($this->usagePrincipaleDeprecie($input->usage)) {
                // La catégorie de la photo est conservée, seule sa place change.
                PhotoPrincipale::placerEnTete($restaurant->ressources(), $resource);
            } else {
                $this->assertUsage($input->usage);
                $resource->changeUsage($input->usage);
            }
        }
        if (array_key_exists('salleId', $payload)) {
            $resource->changeRestaurantSalle(
                $this->room($restaurant, $input->salleId ?? ''),
            );
        }
        if (
            'CONFIG_SALLE_PHOTO' === $resource->usage()
            && null === $resource->restaurantSalle()
        ) {
            throw new ApiProblemException(
                422,
                'room_required',
                'Une photo de salle doit être rattachée à une salle.',
            );
        }
        if (array_key_exists('legende', $payload)) {
            $resource->changeLegende($input->legende);
        }
        if (array_key_exists('source', $payload)) {
            $resource->changeSource($input->source);
        }
        if (array_key_exists('keywords', $payload)) {
            $resource->changeKeywords($input->keywords);
        }
        if (array_key_exists('rightsExpiresAt', $payload)) {
            $resource->changeRightsExpiresAt($input->rightsExpiresAt);
        }
        if (array_key_exists('rightsGranted', $payload)) {
            throw new ApiProblemException(Response::HTTP_FORBIDDEN, 'rights_validation_forbidden', 'La validation des droits est réservée aux validateurs internes du PIM.');
        }

        $transform = false;
        try {
            if (array_key_exists('crop', $payload)) {
                $crop = $input->crop;
                $resource->changeCrop(
                    $crop['x'] ?? null,
                    $crop['y'] ?? null,
                    $crop['width'] ?? null,
                    $crop['height'] ?? null,
                );
                $transform = true;
            }
            if (array_key_exists('rotation', $payload) && null !== $input->rotation) {
                $resource->changeRotation($input->rotation);
                $transform = true;
            }
        } catch (\DomainException $exception) {
            throw new ApiProblemException(
                422,
                'invalid_transformation',
                $exception->getMessage(),
            );
        }

        if ($transform) {
            $this->outbox->enqueue(
                new RegenerateMedia($resource->damAssetId()),
            );
        }
        $this->changed($restaurant);

        return $this->mapper->media($restaurant, $resource);
    }

    private function replace(
        Restaurant $restaurant,
        RessourceLieu $resource,
    ): LieuMediaResource {
        $file = $this->request()->files->get('photo');
        if (!$file instanceof UploadedFile) {
            throw new ApiProblemException(
                422,
                'photo_required',
                'Le champ multipart photo est obligatoire.',
            );
        }

        try {
            $asset = $this->uploader->upload($file, $restaurant->fiche());
        } catch (\DomainException $exception) {
            throw new ApiProblemException(
                422,
                'invalid_media',
                $exception->getMessage(),
            );
        }

        $oldAssetId = $resource->damAssetId();
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
            $this->outbox->enqueue(new DeleteMedia($oldAssetId));
            $this->changed($restaurant);
        } catch (\Throwable $exception) {
            $this->cleanup($asset);
            throw $exception;
        }

        return $this->mapper->media($restaurant, $resource);
    }

    private function resource(
        Restaurant $restaurant,
        string $id,
    ): RessourceLieu {
        $resource = $this->resources->find($id);
        if (
            !$resource instanceof RessourceLieu
            || $resource->fiche() !== $restaurant->fiche()
            || NatureRessource::Photo !== $resource->nature()
        ) {
            throw new ApiProblemException(404, 'not_found', 'Média introuvable.');
        }

        return $resource;
    }

    private function room(
        Restaurant $restaurant,
        ?string $id,
    ): ?RestaurantSalle {
        if (null === $id || '' === trim($id)) {
            return null;
        }
        foreach ($restaurant->salles() as $room) {
            if ($room->id() === $id) {
                return $room;
            }
        }

        throw new ApiProblemException(
            422,
            'foreign_room',
            'La salle doit appartenir au Restaurant.',
        );
    }

    /** @return list<RessourceLieu> */
    private function photos(Restaurant $restaurant): array
    {
        return array_values(
            array_filter(
                $restaurant->ressources()->toArray(),
                static fn (RessourceLieu $resource): bool =>
                    NatureRessource::Photo === $resource->nature(),
            ),
        );
    }

    private function assertUsage(string $usage): void
    {
        if (!in_array(
            $usage,
            ['PHOTO_DIVERSE', 'CONFIG_SALLE_PHOTO'],
            true,
        )) {
            throw new ApiProblemException(
                422,
                'invalid_media_usage',
                'La catégorie est invalide.',
            );
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

    private function changed(Restaurant $restaurant): void
    {
        $restaurant->fiche()->markSystemChanged();
        $this->state->flushAndIndex($restaurant);
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
        return $this->requests->getCurrentRequest()
            ?? throw new \LogicException('Aucune requête HTTP active.');
    }
}
