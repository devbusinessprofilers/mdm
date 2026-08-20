<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\Form\SignalementBugType;
use App\Shared\Service\SignalementBugMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Signalement d'un bug par n'importe quel utilisateur connecté : un titre,
 * une description, et le tout part par mail à l'équipe technique.
 */
final class SignalementBugController extends AbstractController
{
    #[Route('/signaler-un-bug', name: 'app_signalement_bug', methods: ['GET', 'POST'])]
    public function signaler(Request $request, SignalementBugMailer $mailer): Response
    {
        $form = $this->createForm(SignalementBugType::class, [
            // La page d'où vient l'utilisateur, pour situer le bug.
            'page' => $request->isMethod('GET') ? $request->headers->get('referer') : null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{titre: string, description: string, page: ?string} $donnees */
            $donnees = $form->getData();
            $mailer->envoyer(
                $donnees['titre'],
                $donnees['description'],
                $donnees['page'],
                $request->headers->get('User-Agent'),
            );
            $this->addFlash('success', 'Merci, votre signalement a bien été envoyé.');

            return $this->redirectToRoute('app_signalement_bug');
        }

        return $this->render('shared/signalement_bug/form.html.twig', [
            'form' => $form,
        ]);
    }
}
