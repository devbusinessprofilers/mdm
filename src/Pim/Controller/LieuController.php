<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\LieuType;
use App\Pim\Message\IndexFiche;
use App\Pim\ReadModel\FicheCursor;
use App\Pim\Repository\LieuRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Search\SearchQuery;
use App\Shared\Service\SearchEngineInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/lieux', name: 'app_pim_lieu_')]
final class LieuController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, LieuRepository $repository, SearchEngineInterface $searchEngine): Response
    {
        $statusValue = $request->query->getString('status');
        $status = '' === $statusValue ? null : StatutFiche::tryFrom($statusValue);
        if ('' !== $statusValue && null === $status) {
            throw new BadRequestHttpException('Statut de fiche invalide.');
        }

        $text = trim($request->query->getString('q'));
        $cursorValue = $request->query->getString('cursor') ?: null;
        $limit = max(1, min(100, $request->query->getInt('limit', 50)));
        try {
            if ('' !== $text) {
                $filters = ['type' => TypeFiche::Lieu->value];
                if (null !== $status) {
                    $filters['status'] = $status->value;
                }
                $page = $searchEngine->search(new SearchQuery($text, $filters, $limit, $cursorValue));
                $lieux = $repository->findListItemsByIds(array_map(
                    static fn ($result): string => $result->id,
                    $page->results,
                ));
                $resultCount = $page->totalCount;
            } else {
                $page = $repository->findListPage(FicheCursor::decode($cursorValue), $limit, $status);
                $lieux = $page->items;
                $resultCount = null;
            }
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        return $this->render('pim/lieu/index.html.twig', [
            'lieux' => $lieux,
            'next_cursor' => $page->nextCursor,
            'status' => $status,
            'query' => $text,
            'result_count' => $resultCount,
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, OutboxPublisherInterface $outbox): Response
    {
        $lieu = new Lieu();

        return $this->save($request, $lieu, $entityManager, $outbox, true);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Lieu $lieu): Response
    {
        return $this->render('pim/lieu/show.html.twig', ['lieu' => $lieu]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Lieu $lieu, EntityManagerInterface $entityManager, OutboxPublisherInterface $outbox): Response
    {
        return $this->save($request, $lieu, $entityManager, $outbox, false);
    }

    #[Route('/{id}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Lieu $lieu, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-lieu-'.$lieu->id(), (string) $request->request->get('_token'))) {
            $entityManager->remove($lieu);
            $entityManager->flush();
            $this->addFlash('success', 'Lieu supprimé.');
        }

        return $this->redirectToRoute('app_pim_lieu_index');
    }

    private function save(Request $request, Lieu $lieu, EntityManagerInterface $entityManager, OutboxPublisherInterface $outbox, bool $creation): Response
    {
        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($lieu);
            $outbox->enqueue(new IndexFiche($lieu->fiche()->idString()));
            $entityManager->flush();
            $this->addFlash('success', $creation ? 'Lieu créé.' : 'Lieu modifié.');

            return $this->redirectToRoute('app_pim_lieu_show', ['id' => $lieu->id()]);
        }

        return $this->render('pim/lieu/form.html.twig', [
            'form' => $form,
            'lieu' => $lieu,
            'creation' => $creation,
        ]);
    }
}
