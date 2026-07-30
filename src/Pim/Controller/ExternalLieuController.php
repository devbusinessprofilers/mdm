<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Form\LieuType;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\LieuRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/lieux', name: 'api_external_lieu_')]
final class ExternalLieuController extends AbstractController
{
    #[Route('/{id}', name: 'patch', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['PATCH'])]
    public function patch(
        string $id,
        Request $request,
        LieuRepository $lieux,
        EntityManagerInterface $entityManager,
        OutboxPublisherInterface $outbox,
    ): JsonResponse {
        if ('application/merge-patch+json' !== strtolower((string) $request->headers->get('Content-Type'))) {
            return $this->json(['error' => 'unsupported_media_type', 'message' => 'Content-Type application/merge-patch+json requis.'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }
        $lieu = $lieux->find($id);
        if (!$lieu instanceof Lieu) {
            return $this->json(['error' => 'not_found', 'message' => 'Lieu introuvable.'], Response::HTTP_NOT_FOUND);
        }
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => 'invalid_json', 'message' => 'Corps JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }
        if (array_key_exists('ressources', $payload) || array_key_exists('published', $payload) || array_key_exists('status', $payload)) {
            return $this->json(['error' => 'invalid_fields', 'message' => 'Utilisez les routes média dédiées et laissez le PIM gérer le statut.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $form = $this->createForm(LieuType::class, $lieu, ['csrf_protection' => false]);
        try {
            $form->submit($payload, false);
        } catch (\Throwable $exception) {
            return $this->json(['error' => 'invalid_payload', 'message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!$form->isValid()) {
            return $this->json(['error' => 'validation_failed', 'violations' => $this->errors($form)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $lieu->fiche()->publishFromExternal();
        $outbox->enqueue(new IndexFiche($lieu->fiche()->idString()));
        $entityManager->flush();

        return $this->json([
            'id' => $lieu->id(),
            'status' => $lieu->fiche()->status()->value,
            'version' => $lieu->fiche()->version(),
            'updatedAt' => $lieu->fiche()->updatedAt()->format(DATE_ATOM),
        ]);
    }

    /**
     * @param FormInterface<mixed> $form
     * @return list<array{propertyPath: string, message: string}>
     */
    private function errors(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true, true) as $error) {
            $origin = $error->getOrigin();
            $path = [];
            while (null !== $origin && $origin !== $form) {
                array_unshift($path, $origin->getName());
                $origin = $origin->getParent();
            }
            $errors[] = ['propertyPath' => implode('.', $path), 'message' => $error->getMessage()];
        }

        return $errors;
    }
}
