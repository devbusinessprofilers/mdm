<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Account\Entity\User;
use App\Account\Security\FicheVoter;
use App\Dam\Enum\DocumentAccess;
use App\Dam\Enum\DocumentUsage;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\PublishDocument;
use App\Dam\Message\UnpublishDocument;
use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Service\FicheDocumentUploader;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\NatureRessource;
use App\Pim\Form\ActiviteDocumentMetadataType;
use App\Pim\Form\LieuDocumentReplaceType;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Form\ActionType;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Service\PrivateObjectStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/admin/restaurants/{id}/documents',
    name: 'app_pim_restaurant_document_',
    requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
)]
final class RestaurantDocumentController extends AbstractController
{
    public function __construct(
        private readonly RessourceLieuRepository $resources,
        private readonly MediaAssetRepository $assets,
        private readonly FicheDocumentUploader $uploader,
        private readonly EntityManagerInterface $entityManager,
        private readonly OutboxPublisherInterface $outbox,
        private readonly FormFactoryInterface $forms,
    ) {
    }

    #[Route('/{resourceId}/modifier', name: 'update', methods: ['POST'])]
    public function update(
        Request $request,
        Restaurant $restaurant,
        string $resourceId,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $restaurant->fiche());
        $document = $this->document($restaurant, $resourceId);
        $form = $this->forms->createNamed(
            'restaurant_document_metadata_'.$document->id(),
            ActiviteDocumentMetadataType::class,
            [
                'title' => $document->legende(),
                'source' => $document->source(),
                'rightsGranted' => $document->rightsGranted(),
            ],
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->fail($restaurant, 'Le formulaire documentaire est invalide.');
        }

        $data = $form->getData();
        $document->changeLegende(
            is_string($data['title'] ?? null) ? $data['title'] : null,
        );
        $document->changeSource(
            is_string($data['source'] ?? null) ? $data['source'] : null,
        );
        if (true === ($data['rightsGranted'] ?? false)) {
            $document->grantRights($this->actor());
        } else {
            $document->revokeRights();
            $this->unpublish($document);
        }
        $this->changed($restaurant);

        return $this->ok($restaurant, 'Document modifié.');
    }

    #[Route('/{resourceId}/fichier', name: 'replace', methods: ['POST'])]
    public function replace(
        Request $request,
        Restaurant $restaurant,
        string $resourceId,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $restaurant->fiche());
        $document = $this->document($restaurant, $resourceId);
        $form = $this->forms->createNamed(
            'restaurant_document_replace_'.$document->id(),
            LieuDocumentReplaceType::class,
        );
        $form->handleRequest($request);
        $file = $form->isSubmitted() && $form->isValid()
            ? $form->get('document')->getData()
            : null;
        if (!$file instanceof UploadedFile) {
            return $this->fail($restaurant, 'Sélectionnez un document valide.');
        }

        $usage = $document->documentUsage()
            ?? throw $this->createNotFoundException('Usage documentaire invalide.');
        $asset = $this->uploader->upload(
            $file,
            $restaurant->fiche(),
            $usage,
        );
        $oldAssetId = $document->damAssetId();
        try {
            $this->unpublish($document);
            $this->entityManager->persist($asset);
            $document->changeDamAssetId($asset->id());
            $this->outbox->enqueue(new DeleteMedia($oldAssetId));
            $this->changed($restaurant);
        } catch (\Throwable $exception) {
            try {
                $this->uploader->delete($asset);
            } catch (\Throwable) {
            }
            throw $exception;
        }

        return $this->ok($restaurant, 'Fichier remplacé.');
    }

    #[Route('/{resourceId}/publication', name: 'publication', methods: ['POST'])]
    public function publication(
        Request $request,
        Restaurant $restaurant,
        string $resourceId,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        $document = $this->document($restaurant, $resourceId);
        $form = $this->forms->createNamed(
            'restaurant_document_publication_'.$document->id(),
            ActionType::class,
            null,
            [
                'csrf_token_id' =>
                    'restaurant-document-publication-'.$document->id(),
            ],
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->fail($restaurant, 'Action de publication invalide.');
        }

        try {
            if ('published' === $document->publicationStatus()?->value) {
                $this->unpublish($document);
            } else {
                $document->requestPublication();
                $this->outbox->enqueue(new PublishDocument($document->id()));
            }
            $this->changed($restaurant);
        } catch (\DomainException $exception) {
            return $this->fail($restaurant, $exception->getMessage());
        }

        return $this->ok(
            $restaurant,
            'Changement de publication mis en file.',
        );
    }

    #[Route('/{resourceId}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Restaurant $restaurant,
        string $resourceId,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(FicheVoter::EDIT, $restaurant->fiche());
        $document = $this->document($restaurant, $resourceId);
        $form = $this->forms->createNamed(
            'restaurant_document_delete_'.$document->id(),
            ActionType::class,
            null,
            [
                'csrf_token_id' =>
                    'restaurant-document-delete-'.$document->id(),
            ],
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->fail($restaurant, 'Action de suppression invalide.');
        }

        $this->unpublish($document);
        $this->outbox->enqueue(new DeleteMedia($document->damAssetId()));
        $restaurant->removeRessource($document);
        $this->entityManager->remove($document);
        $this->changed($restaurant);

        return $this->ok($restaurant, 'Document supprimé.');
    }

    #[Route('/{resourceId}/download', name: 'download', methods: ['GET'])]
    public function download(
        Restaurant $restaurant,
        string $resourceId,
        PrivateObjectStorageInterface $storage,
    ): RedirectResponse {
        $document = $this->document($restaurant, $resourceId);
        if (DocumentAccess::Private === $document->documentAccess()) {
            $this->denyAccessUnlessGranted('ROLE_BP_VALIDATOR');
        } else {
            $this->denyAccessUnlessGranted(
                FicheVoter::EDIT,
                $restaurant->fiche(),
            );
        }

        $asset = $this->assets->find($document->damAssetId())
            ?? throw $this->createNotFoundException('Fichier DAM introuvable.');

        return $this->redirect(
            $storage->temporaryUrl(
                $asset->originalStorageKey(),
                new \DateTimeImmutable('+10 minutes'),
            ),
        );
    }

    private function document(
        Restaurant $restaurant,
        string $id,
    ): RessourceLieu {
        $document = $this->resources->find($id);
        if (
            !$document instanceof RessourceLieu
            || $document->fiche() !== $restaurant->fiche()
            || NatureRessource::Document !== $document->nature()
            || !in_array(
                $document->documentUsage(),
                [
                    DocumentUsage::RestaurantMenu,
                    DocumentUsage::RoomPlan,
                    DocumentUsage::CommercialSupport,
                ],
                true,
            )
        ) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        return $document;
    }

    private function unpublish(RessourceLieu $document): void
    {
        $key = $document->requestUnpublication();
        if (null !== $key) {
            $this->outbox->enqueue(
                new UnpublishDocument($document->id(), $key),
            );
        }
    }

    private function changed(Restaurant $restaurant): void
    {
        $restaurant->fiche()->markChanged();
        $this->outbox->enqueue(
            new IndexFiche($restaurant->fiche()->idString()),
        );
        $this->entityManager->flush();
    }

    private function actor(): string
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->id() : 'system';
    }

    private function ok(
        Restaurant $restaurant,
        string $message,
    ): RedirectResponse {
        $this->addFlash('success', $message);

        return $this->back($restaurant);
    }

    private function fail(
        Restaurant $restaurant,
        string $message,
    ): RedirectResponse {
        $this->addFlash('error', $message);

        return $this->back($restaurant);
    }

    private function back(Restaurant $restaurant): RedirectResponse
    {
        return $this->redirectToRoute('app_pim_restaurant_edit', [
            'id' => $restaurant->id(),
        ]);
    }
}
