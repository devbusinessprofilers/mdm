<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Entity\User;
use App\Account\Enum\FicheAffiliationRole;
use App\Account\Form\AccountAdminFormFactory;
use App\Account\Form\CollaborateurSearchType;
use App\Account\Service\FicheAffiliationManager;
use App\Pim\Entity\Fiche;
use App\Pim\Repository\FicheAffiliationRepository;
use App\Pim\Repository\FicheCollaborateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/utilisateurs', name: 'app_account_collaborateur_consultation_')]
#[IsGranted('ROLE_BP_VALIDATOR')]
final class CollaborateurConsultationController extends AbstractController
{
    private const PAR_PAGE = 50;

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, FicheCollaborateurRepository $collaborateurs, FicheAffiliationRepository $affiliations, AccountAdminFormFactory $forms): Response
    {
        $searchForm = $this->createForm(CollaborateurSearchType::class);
        $searchForm->handleRequest($request);
        /** @var array{q?: string|null}|null $search */
        $search = $searchForm->getData();
        $q = trim($search['q'] ?? '');

        $total = $collaborateurs->countSearch($q);
        $pages = max(1, (int) ceil($total / self::PAR_PAGE));
        $page = min(max(1, $request->query->getInt('page', 1)), $pages);
        $liste = $collaborateurs->searchPage($q, self::PAR_PAGE, ($page - 1) * self::PAR_PAGE);

        return $this->render('account/consultation/index.html.twig', [
            'collaborateurs' => $liste,
            'affiliations_par_collaborateur' => $affiliations->indexedByCollaborateur($liste),
            'search_form' => $searchForm->createView(),
            'invitation_form' => $forms->collaborateurInvitation($this->generateUrl('app_account_collaborateur_consultation_create'))->createView(),
            'query' => $q,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    #[Route('/inviter', name: 'create', methods: ['POST'])]
    public function create(Request $request, FicheAffiliationManager $manager, AccountAdminFormFactory $forms): Response
    {
        $form = $forms->collaborateurInvitation($this->generateUrl('app_account_collaborateur_consultation_create'));
        $form->handleRequest($request);
        $actor = $this->getUser();
        if (!$actor instanceof User || !$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Invitation invalide.');

            return $this->redirectToRoute('app_account_collaborateur_consultation_index');
        }
        /** @var array{email: string, firstName?: string|null, lastName?: string|null, language: string, fiche: Fiche, role: FicheAffiliationRole, receivesRequests?: bool, traiteContenus?: bool, traitePaiements?: bool} $data */
        $data = $form->getData();
        try {
            $manager->invite(
                $actor, $data['fiche'], $data['email'], $data['role'],
                $data['receivesRequests'] ?? false,
                $data['firstName'] ?? '', $data['lastName'] ?? '', $data['language'],
                traiteContenus: $data['traiteContenus'] ?? false,
                traitePaiements: $data['traitePaiements'] ?? false,
            );
            $this->addFlash('success', 'Utilisateur invité et affilié.');
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_account_collaborateur_consultation_index');
    }
}
