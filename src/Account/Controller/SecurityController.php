<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Form\ConnexionEmailType;
use App\Account\Form\PasswordLoginType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Connexion en deux temps, fidèle à la maquette : l'e-mail d'abord (mémorisé
 * en session), le mot de passe ensuite. Le POST final part vers /connexion,
 * check_path du `form_login` — le firewall reste inchangé.
 */
final class SecurityController extends AbstractController
{
    private const SESSION_EMAIL = 'app_login_email';

    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if (null !== $this->getUser()) {
            return new RedirectResponse($this->generateUrl('app_pim_home'));
        }

        $email = (string) $request->getSession()->get(self::SESSION_EMAIL, '');

        return $this->render('security/login.html.twig', [
            'email_form' => $this->createForm(ConnexionEmailType::class, '' === $email ? null : ['email' => $email], [
                'action' => $this->generateUrl('app_login_email'),
            ])->createView(),
        ]);
    }

    #[Route('/connexion/identifiant', name: 'app_login_email', methods: ['POST'])]
    public function email(Request $request): Response
    {
        $form = $this->createForm(ConnexionEmailType::class, null, [
            'action' => $this->generateUrl('app_login_email'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email: string} $data */
            $data = $form->getData();
            $request->getSession()->set(self::SESSION_EMAIL, $data['email']);

            return $this->redirectToRoute('app_login_password');
        }

        return $this->render('security/login.html.twig', [
            'email_form' => $form->createView(),
        ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/connexion/mot-de-passe', name: 'app_login_password', methods: ['GET'])]
    public function password(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return new RedirectResponse($this->generateUrl('app_pim_home'));
        }

        // Après un échec, le firewall redirige ici : l'e-mail retombe sur le
        // dernier identifiant tenté si la session ne le porte pas (ou plus).
        $email = (string) $request->getSession()->get(self::SESSION_EMAIL, '');
        if ('' === $email) {
            $email = $authenticationUtils->getLastUsername();
        }
        if ('' === $email) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/password.html.twig', [
            'email' => $email,
            'authentication_error' => $authenticationUtils->getLastAuthenticationError(),
            'password_form' => $this->createForm(PasswordLoginType::class, ['email' => $email], [
                'action' => $this->generateUrl('app_login'),
            ])->createView(),
        ]);
    }

    /** Ancien chemin, conservé pour les liens et habitudes : redirige vers /connexion. */
    #[Route('/login', name: 'app_login_legacy', methods: ['GET'])]
    public function loginLegacy(): RedirectResponse
    {
        return $this->redirectToRoute('app_login', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the security firewall.');
    }
}
