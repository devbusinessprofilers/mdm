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
use App\Pim\Api\ActiviteApiMapper;
use App\Pim\Api\Dto\ActiviteResource;
use App\Pim\Api\Dto\LieuMediaResource;
use App\Pim\Api\Dto\MediaOrderInput;
use App\Pim\Api\Dto\MediaPatchInput;
use App\Pim\Api\Exception\ApiProblemException;
use App\Pim\Api\ExternalScopeGuard;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\RessourceLieuRepository;
use App\Pim\Service\PhotoObligations;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProcessorInterface<mixed,LieuMediaResource|ActiviteResource|void> */
final readonly class ActiviteMediaProcessor implements ProcessorInterface
{
    public function __construct(
        private ActiviteApiState $state,
        private ActiviteApiMapper $mapper,
        private FicheImageUploader $uploader,
        private PhotoObligations $photoObligations,
        private RessourceLieuRepository $resources,
        private EntityManagerInterface $em,
        private OutboxPublisherInterface $outbox,
        private RequestStack $requests,
        private ExternalScopeGuard $scopes,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): LieuMediaResource|ActiviteResource|null {
        $this->scopes->requireScope(ExternalScopeGuard::MEDIAS_WRITE);
        $a = $this->state->activite(
            (string) ($uriVariables['activiteId'] ?? ''),
        );
        $this->state->assertVersion($a);
        if (!($operation instanceof HttpOperation)) {
            throw new \LogicException('Une opération HTTP est requise.');
        }
        $method = $operation->getMethod();
        $template = $operation->getUriTemplate() ?? '';

        return $a
            ->fiche()
            ->preserveWorkflowDuring(function () use (
                $data,
                $a,
                $method,
                $template,
                $uriVariables,
            ) {
                if ('POST' === $method && str_ends_with($template, '/medias')) {
                    return $this->upload($a);
                }
                if ('PUT' === $method && str_ends_with($template, '/ordre')) {
                    if (!($data instanceof MediaOrderInput)) {
                        throw new ApiProblemException(400, 'invalid_payload', 'Le tableau ids est obligatoire.');
                    }

                    return $this->order($a, $data);
                }
                $r = $this->resource(
                    $a,
                    (string) ($uriVariables['resourceId'] ?? ''),
                );
                if ('PATCH' === $method) {
                    if (!($data instanceof MediaPatchInput)) {
                        throw new ApiProblemException(400, 'invalid_payload', 'Corps JSON invalide.');
                    }

                    return $this->metadata($a, $r, $data);
                }
                if (
                    'POST' === $method
                    && str_ends_with($template, '/fichier')
                ) {
                    return $this->replace($a, $r);
                }
                if ('DELETE' === $method) {
                    $this->outbox->enqueue(new DeleteMedia($r->damAssetId()));
                    $a->removeRessource($r);
                    $this->em->remove($r);
                    $this->changed($a);

                    return null;
                }
                throw new \LogicException('Opération média inconnue.');
            });
    }

    private function upload(Activite $a): LieuMediaResource
    {
        $maximum = $this->photoObligations->maximum(TypeFiche::Activite);
        if (count($this->photos($a)) >= $maximum) {
            throw new ApiProblemException(422, 'media_limit_reached', sprintf('Une activité ne peut pas contenir plus de %d photos.', $maximum));
        }
        $request = $this->requests->getCurrentRequest();
        if (null === $request) {
            throw new \LogicException('Aucune requete HTTP active.');
        }
        $file = $request->files->get('photo');
        if (!($file instanceof UploadedFile)) {
            throw new ApiProblemException(422, 'photo_required', 'Le champ multipart photo est obligatoire.');
        }
        $usage = $request->request->getString('usage', 'PHOTO_DIVERSE');
        $this->assertUsage($a, $usage);
        try {
            $asset = $this->uploader->upload($file, $a->fiche());
        } catch (\DomainException $e) {
            throw new ApiProblemException(422, 'invalid_media', $e->getMessage());
        }
        $r = new RessourceLieu();
        $r->changeDamAssetId($asset->id());
        $r->changeNature(NatureRessource::Photo);
        $r->changeUsage($usage);
        $legend = $request->request->get('legende');
        $r->changeLegende(is_string($legend) ? $legend : null);
        $r->changePosition(count($this->photos($a)));
        $a->addRessource($r);
        try {
            $this->em->persist($asset);
            $this->outbox->enqueue(
                new MediaUploaded(
                    $asset->id(),
                    $asset->originalStorageKey(),
                    $asset->checksum(),
                    ImageVariantRegistry::names(),
                ),
            );
            $this->changed($a);
        } catch (\Throwable $e) {
            $this->cleanup($asset);
            throw $e;
        }

        return $this->mapper->media($a, $r);
    }

    private function order(
        Activite $a,
        MediaOrderInput $input,
    ): ActiviteResource {
        $photos = $this->photos($a);
        $known = array_map(
            static fn (RessourceLieu $r): string => $r->id(),
            $photos,
        );
        if (
            count($input->ids) !== count(array_unique($input->ids))
            || count($input->ids) !== count($known)
            || [] !== array_diff($input->ids, $known)
            || [] !== array_diff($known, $input->ids)
        ) {
            throw new ApiProblemException(422, 'invalid_media_order', 'L’ordre transmis ne correspond pas aux photos de l’activité.');
        }
        $byId = [];
        foreach ($photos as $r) {
            $byId[$r->id()] = $r;
        }
        foreach ($input->ids as $position => $id) {
            $byId[$id]->changePosition($position);
        }
        $this->changed($a);

        return $this->mapper->activite($a);
    }

    private function metadata(
        Activite $a,
        RessourceLieu $r,
        MediaPatchInput $input,
    ): LieuMediaResource {
        $raw = json_decode((string) $this->request()->getContent(), true);
        $raw = is_array($raw) ? $raw : [];
        if (array_key_exists('usage', $raw)) {
            if (!is_string($input->usage)) {
                throw new ApiProblemException(422, 'invalid_media_usage', 'La catégorie est invalide.');
            }
            $this->assertUsage($a, $input->usage, $r);
            $r->changeUsage($input->usage);
        }
        if (array_key_exists('legende', $raw)) {
            $r->changeLegende($input->legende);
        }
        if (array_key_exists('source', $raw)) {
            $r->changeSource($input->source);
        }
        if (array_key_exists('keywords', $raw)) {
            $r->changeKeywords($input->keywords);
        }
        if (array_key_exists('rightsExpiresAt', $raw)) {
            $r->changeRightsExpiresAt($input->rightsExpiresAt);
        }
        if (array_key_exists('rightsGranted', $raw)) {
            throw new ApiProblemException(403, 'rights_validation_forbidden', 'La validation des droits est réservée aux validateurs internes du PIM.');
        }
        $transform = false;
        try {
            if (array_key_exists('crop', $raw)) {
                $crop = $input->crop;
                $r->changeCrop(
                    $crop['x'] ?? null,
                    $crop['y'] ?? null,
                    $crop['width'] ?? null,
                    $crop['height'] ?? null,
                );
                $transform = true;
            }
            if (
                array_key_exists('rotation', $raw)
                && null !== $input->rotation
            ) {
                $r->changeRotation($input->rotation);
                $transform = true;
            }
        } catch (\DomainException $e) {
            throw new ApiProblemException(422, 'invalid_transformation', $e->getMessage());
        }
        if ($transform) {
            $this->outbox->enqueue(new RegenerateMedia($r->damAssetId()));
        }
        $this->changed($a);

        return $this->mapper->media($a, $r);
    }

    private function replace(Activite $a, RessourceLieu $r): LieuMediaResource
    {
        $file = $this->request()->files->get('photo');
        if (!($file instanceof UploadedFile)) {
            throw new ApiProblemException(422, 'photo_required', 'Le champ multipart photo est obligatoire.');
        }
        try {
            $asset = $this->uploader->upload($file, $a->fiche());
        } catch (\DomainException $e) {
            throw new ApiProblemException(422, 'invalid_media', $e->getMessage());
        }
        $old = $r->damAssetId();
        try {
            $this->em->persist($asset);
            $r->changeDamAssetId($asset->id());
            $this->outbox->enqueue(
                new MediaUploaded(
                    $asset->id(),
                    $asset->originalStorageKey(),
                    $asset->checksum(),
                    ImageVariantRegistry::names(),
                ),
            );
            $this->outbox->enqueue(new DeleteMedia($old));
            $this->changed($a);
        } catch (\Throwable $e) {
            $this->cleanup($asset);
            throw $e;
        }

        return $this->mapper->media($a, $r);
    }

    private function resource(Activite $a, string $id): RessourceLieu
    {
        $r = $this->resources->find($id);
        if (
            !($r instanceof RessourceLieu)
            || $r->fiche() !== $a->fiche()
            || NatureRessource::Photo !== $r->nature()
        ) {
            throw new ApiProblemException(404, 'not_found', 'Média introuvable.');
        }

        return $r;
    }

    /** @return list<RessourceLieu> */
    private function photos(Activite $a): array
    {
        return array_values(
            array_filter(
                $a->ressources()->toArray(),
                static fn (RessourceLieu $r): bool => NatureRessource::Photo ===
                    $r->nature(),
            ),
        );
    }

    private function assertUsage(
        Activite $a,
        string $usage,
        ?RessourceLieu $current = null,
    ): void {
        if (!in_array($usage, ['PHOTO_PRINCIPALE', 'PHOTO_DIVERSE'], true)) {
            throw new ApiProblemException(422, 'invalid_media_usage', 'La catégorie est invalide.');
        }
        if ('PHOTO_PRINCIPALE' === $usage) {
            foreach ($this->photos($a) as $r) {
                if ($r !== $current && 'PHOTO_PRINCIPALE' === $r->usage()) {
                    throw new ApiProblemException(422, 'main_media_exists', 'Une seule photo principale est autorisée.');
                }
            }
        }
    }

    private function changed(Activite $a): void
    {
        $a->fiche()->markSystemChanged();
        $this->state->flushAndIndex($a);
    }

    private function cleanup(MediaAsset $asset): void
    {
        try {
            $this->uploader->delete($asset);
        } catch (\Throwable) {
        }
    }

    private function request(): \Symfony\Component\HttpFoundation\Request
    {
        return $this->requests->getCurrentRequest() ??
            throw new \LogicException('Aucune requête HTTP active.');
    }
}
