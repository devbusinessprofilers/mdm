<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Dam\Entity\MediaDuplicateAlert;
use App\Dam\Enum\DuplicateReviewStatus;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\RegenerateMedia;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Repository\MediaDuplicateAlertRepository;
use App\Dam\Message\UnpublishDocument;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\RessourceLieuRepository;
use App\Pim\Service\InternalFicheMutationPolicy;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class DamResourceManager
{
    public function __construct(
        private MediaDuplicateAlertRepository $alerts,
        private MediaAssetRepository $assets,
        private RessourceLieuRepository $resources,
        private InternalFicheMutationPolicy $mutationPolicy,
        private OutboxPublisherInterface $outbox,
        private EntityManagerInterface $entityManager,
        private AuthorizationCheckerInterface $authorization,
    ) {}

    public function acceptDuplicate(string $alertId, string $actor): void
    {
        $this->assertValidator();
        $alert = $this->alert($alertId);
        if (DuplicateReviewStatus::Pending === $alert->status()) {
            $alert->accept($actor);
            $this->entityManager->flush();
        }
    }

    public function deleteDuplicate(string $alertId, string $actor): void
    {
        $this->assertValidator();
        $alert = $this->alert($alertId);
        $resource = $this->resources->find($alert->resourceId());
        if (!$resource instanceof RessourceLieu || NatureRessource::Photo !== $resource->nature() || $resource->damAssetId() !== $alert->media()->id()) {
            throw new \DomainException("L'image en doublon n'est plus rattachée à cette fiche.");
        }
        $fiche = $resource->fiche() ?? throw new \DomainException('La ressource ne possède plus de fiche.');
        $this->mutationPolicy->execute($fiche, function () use ($alert, $resource, $fiche, $actor): void {
            if (null !== $resource->lieu()) {
                $resource->lieu()->removeRessource($resource);
            } else {
                $fiche->removeResource($resource);
            }
            $this->entityManager->remove($resource);
            $alert->resolve($actor);
            $this->outbox->enqueue(new DeleteMedia($alert->media()->id()));
            $this->outbox->enqueue(new IndexFiche($fiche->idString()));
            $fiche->markChanged();
            $this->entityManager->flush();
        });
    }

    public function changeRights(string $resourceId, bool $grant, string $actor): void
    {
        $this->assertValidator();
        $resource = $this->resources->find($resourceId);
        if (!$resource instanceof RessourceLieu || null === $resource->fiche()) {
            throw new \DomainException('Ressource DAM introuvable.');
        }
        $fiche = $resource->fiche();
        $this->mutationPolicy->execute($fiche, function () use ($resource, $fiche, $grant, $actor): void {
            if ($grant) {
                $resource->grantRights($actor);
            } else {
                $resource->revokeRights();
                if (NatureRessource::Document === $resource->nature()) {
                    $publicKey = $resource->requestUnpublication();
                    if (null !== $publicKey) {
                        $this->outbox->enqueue(new UnpublishDocument($resource->id(), $publicKey));
                    }
                }
            }
            $fiche->markChanged();
            $this->outbox->enqueue(new IndexFiche($fiche->idString()));
            $this->entityManager->flush();
        });
    }

    public function retry(string $mediaId): void
    {
        $media = $this->assets->find($mediaId);
        if (null === $media) {
            throw new \DomainException('Média DAM introuvable.');
        }
        $this->outbox->enqueue(new RegenerateMedia($media->id()));
        $this->entityManager->flush();
    }

    private function alert(string $id): MediaDuplicateAlert
    {
        $alert = $this->alerts->find($id);
        if (!$alert instanceof MediaDuplicateAlert) {
            throw new \DomainException('Alerte de doublon introuvable.');
        }

        return $alert;
    }

    private function assertValidator(): void
    {
        if (!$this->authorization->isGranted('ROLE_BP_VALIDATOR')) {
            throw new AccessDeniedException('Cette action est réservée aux validateurs.');
        }
    }
}
