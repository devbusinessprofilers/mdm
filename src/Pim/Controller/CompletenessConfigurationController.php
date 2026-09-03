<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use App\Pim\Completeness\CompletenessFieldCatalog;
use App\Pim\Entity\CompletenessFieldConfiguration;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\CompletenessConfigurationType;
use App\Pim\Form\CompletenessSearchType;
use App\Pim\Repository\CompletenessConfigurationAuditRepository;
use App\Pim\Repository\CompletenessFieldConfigurationRepository;
use App\Pim\Service\CompletenessAdminProvider;
use App\Pim\Service\CompletenessConfigurationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/qualite/completude', name: 'app_pim_completeness_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class CompletenessConfigurationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        CompletenessFieldCatalog $catalog,
        CompletenessAdminProvider $provider,
    ): Response {
        $searchForm = $this->createForm(CompletenessSearchType::class, [
            'type' => TypeFiche::tryFrom($request->query->getString('type')),
            'q' => $request->query->getString('q'),
        ]);
        $searchForm->handleRequest($request);
        /** @var array{type?: TypeFiche|null, q?: string|null} $search */
        $search = $searchForm->getData();
        $selectedType = $search['type'] ?? null;
        $query = trim((string) ($search['q'] ?? ''));

        return $this->render('pim/completeness/index.html.twig', ['rows' => $provider->rows($selectedType, $query), 'types' => $catalog->supportedTypes(), 'selected_type' => $selectedType, 'query' => $request->query->getString('q'), 'status' => $provider->status(), 'search_form' => $searchForm->createView()]);
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        CompletenessFieldConfigurationRepository $repository,
        CompletenessFieldCatalog $catalog,
        CompletenessConfigurationAuditRepository $auditRepository,
        CompletenessConfigurationManager $manager,
    ): Response {
        $configuration = $repository->find($id);
        if (!$configuration instanceof CompletenessFieldConfiguration) {
            throw $this->createNotFoundException();
        }
        $definition = $catalog->find($configuration->ficheType(), $configuration->fieldCode());
        if (null === $definition) {
            throw $this->createNotFoundException('Champ absent du catalogue.');
        }
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
            if (!$manager->update($configuration, $submitted, $this->getUser()?->getUserIdentifier() ?? 'system')) {
                $this->addFlash('info', 'Aucune modification détectée. Aucun recalcul planifié.');

                return $this->redirectToRoute('app_pim_completeness_index', ['type' => $configuration->ficheType()->value]);
            }
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
