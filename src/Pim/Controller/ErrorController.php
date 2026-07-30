<?php

namespace App\Pim\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ErrorController extends AbstractController
{
    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @throws \Throwable
     */
    public function show(\Throwable $exception): Response
    {
        if ($_ENV['APP_DEBUG'] ?? false) {
            throw $exception; // Laissez Symfony gérer l'erreur en mode debug
        }

        $statusCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

        return $this->render('error/error.html.twig', [
            'status_code' => $statusCode,
            'status_text' => Response::$statusTexts[$statusCode] ?? 'Error',
            'go_back_title' => $this->translator->trans('error.error_page.go_back_title', [], 'error')
        ]);
    }
}
