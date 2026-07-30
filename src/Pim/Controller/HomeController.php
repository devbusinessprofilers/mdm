<?php

declare(strict_types=1);

namespace App\Pim\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('pim/index.html.twig');
    }
}
