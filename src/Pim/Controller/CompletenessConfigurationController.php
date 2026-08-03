<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Completeness\CompletenessFieldCatalog;
use App\Pim\Completeness\CompletenessRecalculationScheduler;
use App\Pim\Entity\CompletenessConfigurationRevision;
use App\Pim\Entity\CompletenessConfigurationAudit;
use App\Pim\Entity\CompletenessFieldConfiguration;
use App\Pim\Enum\CompletenessFormula;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\CompletenessConfigurationType;
use App\Pim\Form\CompletenessSearchType;
use App\Pim\Repository\CompletenessFieldConfigurationRepository;
use App\Pim\Repository\CompletenessConfigurationAuditRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/completude', name: 'app_pim_completeness_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class CompletenessConfigurationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        CompletenessFieldCatalog $catalog,
        CompletenessFieldConfigurationRepository $repository,
        EntityManagerInterface $entityManager,
        Connection $connection,
    ): Response {
        $searchForm = $this->createForm(CompletenessSearchType::class, [
            'type' => TypeFiche::tryFrom($request->query->getString('type')),
            'q' => $request->query->getString('q'),
        ]);
        $searchForm->handleRequest($request);
        /** @var array{type?: TypeFiche|null, q?: string|null} $search */
        $search = $searchForm->getData();
        $selectedType = $search['type'] ?? null;
        $query = mb_strtolower(trim((string) ($search['q'] ?? '')));
        $configurations = null === $selectedType
            ? $repository->findBy([], ['ficheType' => 'ASC', 'fieldCode' => 'ASC'])
            : $repository->findBy(['ficheType' => $selectedType], ['fieldCode' => 'ASC']);
        $rows = [];
        foreach ($configurations as $configuration) {
            $definition = $catalog->find($configuration->ficheType(), $configuration->fieldCode());
            if (null === $definition) { continue; }
            if ('' !== $query && !str_contains(mb_strtolower($configuration->fieldCode().' '.$configuration->label()), $query)) { continue; }
            $rows[] = ['configuration' => $configuration, 'definition' => $definition, 'effective_target' => $configuration->targetLengthOverride() ?? $definition->defaultTargetLength];
        }

        $status = [];
        $tables = [TypeFiche::Lieu->value => 'pim_lieu', TypeFiche::Activite->value => 'pim_activite', TypeFiche::Restaurant->value => 'pim_restaurant', TypeFiche::ServiceEvenementiel->value => 'pim_service_evenementiel'];
        foreach ($catalog->supportedTypes() as $type) {
            $table = $tables[$type->value] ?? null;
            if (null === $table) { continue; }
            $revision = $entityManager->find(CompletenessConfigurationRevision::class, $type);
            $current = $revision?->revision() ?? 1;
            $status[$type->value] = [
                'revision' => $current,
                'pending' => (int) $connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s WHERE completeness_revision < ?', $table), [$current]),
            ];
        }

        return $this->render('pim/completeness/index.html.twig', ['rows' => $rows, 'types' => $catalog->supportedTypes(), 'selected_type' => $selectedType, 'query' => $request->query->getString('q'), 'status' => $status, 'search_form' => $searchForm->createView()]);
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        CompletenessFieldConfigurationRepository $repository,
        CompletenessFieldCatalog $catalog,
        CompletenessRecalculationScheduler $scheduler,
        CompletenessConfigurationAuditRepository $auditRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $configuration = $repository->find($id);
        if (!$configuration instanceof CompletenessFieldConfiguration) { throw $this->createNotFoundException(); }
        $definition = $catalog->find($configuration->ficheType(), $configuration->fieldCode());
        if (null === $definition) { throw $this->createNotFoundException('Champ absent du catalogue.'); }
        $data = [
            'formula' => $configuration->formula(), 'weight' => $configuration->weight(), 'targetLengthOverride' => $configuration->targetLengthOverride(),
            'active' => $configuration->active(), 'marketplace' => $configuration->marketplace(), 'thematicSites' => $configuration->thematicSites(),
            'salesforce' => $configuration->salesforce(), 'providerPortal' => $configuration->providerPortal(),
        ];
        $form = $this->createForm(CompletenessConfigurationType::class, $data, ['default_target' => $definition->defaultTargetLength]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $submitted */
            $submitted = $form->getData();
            $before = $configuration->snapshot();
            $configuration->configure(
                $submitted['formula'] instanceof CompletenessFormula ? $submitted['formula'] : CompletenessFormula::Presence,
                (float) $submitted['weight'], null === $submitted['targetLengthOverride'] ? null : (int) $submitted['targetLengthOverride'],
                (bool) $submitted['active'], (bool) $submitted['marketplace'], (bool) $submitted['thematicSites'], (bool) $submitted['salesforce'], (bool) $submitted['providerPortal'],
            );
            if ($before === $configuration->snapshot()) {
                $this->addFlash('info', 'Aucune modification détectée. Aucun recalcul planifié.');

                return $this->redirectToRoute('app_pim_completeness_index', ['type' => $configuration->ficheType()->value]);
            }
            $revision = $scheduler->schedule($configuration->ficheType());
            $actor = $this->getUser()?->getUserIdentifier() ?? 'system';
            $entityManager->persist(new CompletenessConfigurationAudit(
                $configuration->ficheType(),
                $configuration->fieldCode(),
                $revision,
                $actor,
                'admin',
                $before,
                $configuration->snapshot(),
            ));
            $entityManager->flush();
            $this->addFlash('success', 'Configuration enregistrée et recalcul planifié.');

            return $this->redirectToRoute('app_pim_completeness_index', ['type' => $configuration->ficheType()->value]);
        }

        return $this->render('pim/completeness/edit.html.twig', [
            'form' => $form,
            'configuration' => $configuration,
            'definition' => $definition,
            'audits' => $auditRepository->recentForField($configuration->ficheType(), $configuration->fieldCode()),
        ]);
    }
}
