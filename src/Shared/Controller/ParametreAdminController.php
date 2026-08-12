<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\Entity\Parametre;
use App\Shared\Form\ActionType;
use App\Shared\Form\ParametreType;
use App\Shared\Repository\ParametreRepository;
use App\Shared\Service\ParametreAdminManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration des paramètres applicatifs : la variable d'environnement
 * reste la valeur par défaut, la table `parametre` la surcharge à chaud.
 */
#[Route('/admin/parametres', name: 'app_parametre_')]
#[IsGranted('ROLE_ADMIN')]
final class ParametreAdminController extends AbstractController
{
    private const NOM_REQUIREMENT = '[a-z0-9_.]+';

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ParametreAdminManager $manager, FormFactoryInterface $forms): Response
    {
        $lignes = [];
        foreach ($manager->lignes() as $ligne) {
            $parametre = $ligne['parametre'];
            $ligne['reset_form'] = !$parametre->estSurcharge() ? null : $forms->createNamed('action', ActionType::class, null, [
                'action' => $this->generateUrl('app_parametre_reset', ['nom' => $parametre->nom()]),
                'button_label' => 'Revenir au défaut',
                'csrf_token_id' => 'parametre-reset-'.$parametre->nom(),
            ])->createView();
            $lignes[] = $ligne;
        }

        return $this->render('shared/parametre/index.html.twig', ['lignes' => $lignes]);
    }

    #[Route('/{nom}/modifier', name: 'edit', requirements: ['nom' => self::NOM_REQUIREMENT], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        string $nom,
        ParametreRepository $parametres,
        ParametreAdminManager $manager,
    ): Response {
        $parametre = $parametres->parNom($nom);
        if (!$parametre instanceof Parametre) {
            throw $this->createNotFoundException('Paramètre introuvable.');
        }
        $form = $this->createForm(ParametreType::class, ['valeur' => $manager->donnees($parametre)], ['type' => $parametre->type()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            $manager->surcharger($parametre, $data['valeur']);
            $this->addFlash('success', sprintf('Paramètre « %s » mis à jour.', $parametre->nom()));

            return $this->redirectToRoute('app_parametre_index');
        }

        return $this->render('shared/parametre/form.html.twig', [
            'form' => $form->createView(),
            'parametre' => $parametre,
            'defaut' => $manager->defaut($nom),
        ]);
    }

    #[Route('/{nom}/defaut', name: 'reset', requirements: ['nom' => self::NOM_REQUIREMENT], methods: ['POST'])]
    public function reset(
        Request $request,
        string $nom,
        ParametreRepository $parametres,
        ParametreAdminManager $manager,
        FormFactoryInterface $forms,
    ): Response {
        $parametre = $parametres->parNom($nom);
        if (!$parametre instanceof Parametre) {
            throw $this->createNotFoundException('Paramètre introuvable.');
        }
        $form = $forms->createNamed('action', ActionType::class, null, [
            'button_label' => 'Revenir au défaut',
            'csrf_token_id' => 'parametre-reset-'.$nom,
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Action invalide.');
        }
        $manager->revenirAuDefaut($parametre);
        $this->addFlash('success', sprintf('Paramètre « %s » revenu à sa valeur par défaut.', $parametre->nom()));

        return $this->redirectToRoute('app_parametre_index');
    }
}
