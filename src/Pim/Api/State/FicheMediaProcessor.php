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
use App\Pim\Api\Dto\ActiviteResource;
use App\Pim\Api\Dto\FicheMediaResource;
use App\Pim\Api\Dto\LieuResource;
use App\Pim\Api\Dto\MediaOrderInput;
use App\Pim\Api\Dto\MediaPatchInput;
use App\Pim\Api\Dto\RestaurantResource;
use App\Pim\Api\Dto\ServiceEvenementielResource;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\ExternalScopeGuard;
use App\Pim\Api\FicheApiMapper;
use App\Pim\Api\ProfilApiGamme;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Lieu\Salle;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Restaurant\RestaurantSalle;
use App\Pim\Entity\Service\ServiceEvenementiel;
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

/**
 * Photos des fiches dans l'API externe, toutes gammes : dépôt, ordre,
 * métadonnées (recadrage, rotation), remplacement du fichier et suppression.
 * Chaque écriture exige If-Match et laisse le workflow de la fiche intact.
 * La gamme (catégories acceptées, rattachement à une salle) vient de
 * ProfilApiGamme.
 *
 * @implements ProcessorInterface<mixed, FicheMediaResource|LieuResource|RestaurantResource|ActiviteResource|ServiceEvenementielResource|void>
 */
final readonly class FicheMediaProcessor implements ProcessorInterface
{
    public function __construct(
        private FicheApiState $state,
        private FicheApiMapper $mapper,
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

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FicheMediaResource|LieuResource|RestaurantResource|ActiviteResource|ServiceEvenementielResource|null
    {
        $this->scopes->requireScope(ExternalScopeGuard::MEDIAS_WRITE);
        $profil = ProfilApiGamme::depuisUriVariables($uriVariables);
        $entite = $this->state->entite($profil->type, $profil->id($uriVariables));
        $this->state->assertVersion($entite);
        if (!$operation instanceof HttpOperation) {
            throw new \LogicException('Une opération HTTP est requise.');
        }
        $method = $operation->getMethod();
        $template = $operation->getUriTemplate() ?? '';

        return $entite->fiche()->preserveWorkflowDuring(function () use ($data, $profil, $entite, $method, $template, $uriVariables): FicheMediaResource|LieuResource|RestaurantResource|ActiviteResource|ServiceEvenementielResource|null {
            if ('POST' === $method && str_ends_with($template, '/medias')) {
                return $this->upload($profil, $entite);
            }
            if ('PUT' === $method && str_ends_with($template, '/ordre')) {
                if (!$data instanceof MediaOrderInput) {
                    throw new ApiProblemException(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'Le tableau ids est obligatoire.');
                }

                return $this->order($entite, $data);
            }
            $resource = $this->resource($entite, (string) ($uriVariables['resourceId'] ?? ''));
            if ('PATCH' === $method) {
                if (!$data instanceof MediaPatchInput) {
                    throw new ApiProblemException(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'Corps JSON invalide.');
                }

                return $this->metadata($profil, $entite, $resource, $data);
            }
            if ('POST' === $method && str_ends_with($template, '/fichier')) {
                return $this->replace($entite, $resource);
            }
            if ('DELETE' === $method) {
                $this->outbox->enqueue(new DeleteMedia($resource->damAssetId()));
                $entite->removeRessource($resource);
                $this->entityManager->remove($resource);
                $this->changed($entite);

                return null;
            }
            throw new \LogicException('Opération média API inconnue.');
        });
    }

    private function upload(ProfilApiGamme $profil, Lieu|Restaurant|Activite|ServiceEvenementiel $entite): FicheMediaResource
    {
        $maximum = $this->photoObligations->maximum($profil->type);
        if (count($this->photos($entite)) >= $maximum) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'media_limit_reached', sprintf('Une fiche %s ne peut pas contenir plus de %d photos.', $profil->type->libelle(), $maximum));
        }
        $request = $this->request();
        $file = $request->files->get('photo');
        if (!$file instanceof UploadedFile) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'photo_required', 'Le champ multipart photo est obligatoire.');
        }
        $usage = $request->request->getString('usage', PhotoUsageCatalog::DEFAUT);
        $enTete = $this->usagePrincipaleDeprecie($usage);
        if ($enTete) {
            $usage = PhotoUsageCatalog::DEFAUT;
        }
        $this->assertUsage($profil, $usage);
        $salle = $this->salle($profil, $entite, $request->request->getString('salleId'));
        $this->assertSalleDePhoto($profil, $usage, $salle);
        try {
            $asset = $this->uploader->upload($file, $entite->fiche());
        } catch (\DomainException $exception) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_media', $exception->getMessage());
        }
        $resource = new RessourceLieu();
        $resource->changeDamAssetId($asset->id());
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage($usage);
        $resource->rattacherSalle($salle);
        $legende = $request->request->get('legende');
        $resource->changeLegende(is_string($legende) ? $legende : null);
        $resource->changePosition(count($this->photos($entite)));
        try {
            $entite->addRessource($resource);
            if ($enTete) {
                PhotoPrincipale::placerEnTete($entite->ressources(), $resource);
            }
            $this->entityManager->persist($asset);
            $this->outbox->enqueue(new MediaUploaded($asset->id(), $asset->originalStorageKey(), $asset->checksum(), ImageVariantRegistry::names()));
            $this->changed($entite);
        } catch (\Throwable $exception) {
            $this->cleanup($asset);
            throw $exception;
        }

        return $this->mapper->media($entite, $resource);
    }

    private function order(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, MediaOrderInput $input): LieuResource|RestaurantResource|ActiviteResource|ServiceEvenementielResource
    {
        $photos = $this->photos($entite);
        $known = array_map(static fn (RessourceLieu $resource): string => $resource->id(), $photos);
        if (
            count($input->ids) !== count(array_unique($input->ids))
            || count($input->ids) !== count($known)
            || [] !== array_diff($input->ids, $known)
            || [] !== array_diff($known, $input->ids)
        ) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_media_order', 'L’ordre transmis ne correspond pas aux photos de la fiche.');
        }
        $byId = [];
        foreach ($photos as $photo) {
            $byId[$photo->id()] = $photo;
        }
        foreach ($input->ids as $position => $id) {
            $byId[$id]->changePosition($position);
        }
        $this->changed($entite);

        return $this->mapper->fiche($entite);
    }

    private function metadata(ProfilApiGamme $profil, Lieu|Restaurant|Activite|ServiceEvenementiel $entite, RessourceLieu $resource, MediaPatchInput $input): FicheMediaResource
    {
        $payload = json_decode((string) $this->request()->getContent(), true);
        $payload = is_array($payload) ? $payload : [];
        if (\array_key_exists('usage', $payload)) {
            if (!is_string($input->usage)) {
                throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_media_usage', 'La catégorie de photo est invalide.');
            }
            if ($this->usagePrincipaleDeprecie($input->usage)) {
                // La catégorie de la photo est conservée, seule sa place change.
                PhotoPrincipale::placerEnTete($entite->ressources(), $resource);
            } else {
                $this->assertUsage($profil, $input->usage);
                $resource->changeUsage($input->usage);
            }
        }
        if (\array_key_exists('salleId', $payload) && $profil->avecSalles()) {
            $resource->rattacherSalle($this->salle($profil, $entite, $input->salleId ?? ''));
        }
        $this->assertSalleDePhoto($profil, $resource->usage(), $resource->salleRattachee());
        if (\array_key_exists('legende', $payload)) {
            $resource->changeLegende($input->legende);
        }
        if (\array_key_exists('source', $payload)) {
            $resource->changeSource($input->source);
        }
        if (\array_key_exists('keywords', $payload)) {
            $resource->changeKeywords($input->keywords);
        }
        if (\array_key_exists('rightsExpiresAt', $payload)) {
            $resource->changeRightsExpiresAt($input->rightsExpiresAt);
        }
        if (\array_key_exists('rightsGranted', $payload)) {
            throw new ApiProblemException(Response::HTTP_FORBIDDEN, 'rights_validation_forbidden', 'La validation des droits est réservée aux validateurs internes du PIM.');
        }
        $transformation = false;
        try {
            if (\array_key_exists('crop', $payload)) {
                $crop = $input->crop;
                $resource->changeCrop($crop['x'] ?? null, $crop['y'] ?? null, $crop['width'] ?? null, $crop['height'] ?? null);
                $transformation = true;
            }
            if (\array_key_exists('rotation', $payload) && null !== $input->rotation) {
                $resource->changeRotation($input->rotation);
                $transformation = true;
            }
        } catch (\DomainException $exception) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_transformation', $exception->getMessage());
        }
        if ($transformation) {
            $this->outbox->enqueue(new RegenerateMedia($resource->damAssetId()));
        }
        $this->changed($entite);

        return $this->mapper->media($entite, $resource);
    }

    private function replace(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, RessourceLieu $resource): FicheMediaResource
    {
        $file = $this->request()->files->get('photo');
        if (!$file instanceof UploadedFile) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'photo_required', 'Le champ multipart photo est obligatoire.');
        }
        try {
            $asset = $this->uploader->upload($file, $entite->fiche());
        } catch (\DomainException $exception) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_media', $exception->getMessage());
        }
        $old = $resource->damAssetId();
        try {
            $this->entityManager->persist($asset);
            $resource->changeDamAssetId($asset->id());
            $this->outbox->enqueue(new MediaUploaded($asset->id(), $asset->originalStorageKey(), $asset->checksum(), ImageVariantRegistry::names()));
            $this->outbox->enqueue(new DeleteMedia($old));
            $this->changed($entite);
        } catch (\Throwable $exception) {
            $this->cleanup($asset);
            throw $exception;
        }

        return $this->mapper->media($entite, $resource);
    }

    private function resource(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, string $id): RessourceLieu
    {
        $resource = $this->resources->find($id);
        if (!$resource instanceof RessourceLieu || $resource->fiche() !== $entite->fiche() || NatureRessource::Photo !== $resource->nature()) {
            throw new ApiProblemException(Response::HTTP_NOT_FOUND, 'not_found', 'Média introuvable.');
        }

        return $resource;
    }

    /** @return list<RessourceLieu> */
    private function photos(Lieu|Restaurant|Activite|ServiceEvenementiel $entite): array
    {
        $photos = [];
        foreach ($entite->fiche()->resources() as $resource) {
            if (NatureRessource::Photo === $resource->nature()) {
                $photos[] = $resource;
            }
        }

        return $photos;
    }

    /** Salle demandée par `salleId` (vide : aucune) ; elle doit appartenir à la fiche. */
    private function salle(ProfilApiGamme $profil, Lieu|Restaurant|Activite|ServiceEvenementiel $entite, string $id): Salle|RestaurantSalle|null
    {
        if ('' === trim($id) || !$profil->avecSalles() || !($entite instanceof Lieu || $entite instanceof Restaurant)) {
            return null;
        }
        foreach ($entite->salles() as $salle) {
            if ($salle->id() === $id) {
                return $salle;
            }
        }
        throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'foreign_room', 'La salle doit appartenir à la fiche.');
    }

    /**
     * Une photo de salle de Restaurant est rattachée à une salle (dépôt et
     * modification). Le Lieu garde son contrat historique : le portail n'a
     * jamais transmis de salle pour ses photos de salle.
     */
    private function assertSalleDePhoto(ProfilApiGamme $profil, string $usage, Salle|RestaurantSalle|null $salle): void
    {
        if (PhotoUsageCatalog::SALLE === $usage && TypeFiche::Restaurant === $profil->type && null === $salle) {
            throw new ApiProblemException(Response::HTTP_UNPROCESSABLE_ENTITY, 'room_required', 'Une photo de salle doit être rattachée à une salle.');
        }
    }

    private function assertUsage(ProfilApiGamme $profil, string $usage): void
    {
        if (!$profil->photoAutorisee($usage)) {
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
