<?php

namespace App\Pim\Controller\ProviderPortal;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReviewController extends AbstractController
{
    #[Route(path: 'portal/reviews', name: 'provider_portal_review_received')]
    public function index(): Response
    {
        return $this->render('provider_portal/pages/online/review/received.html.twig', [
            'globalScore' => 4.95,
            'scores' => [
                '5' => 55,
                '4' => 20,
                '3' => 10,
                '2' => 10,
                '1' => 5,
            ],
            'sections' => [
                [
                    'label' => 'page.review.section.relationship',
                    'icon' => 'headset',
                    'score' => 5.0,
                ],
                [
                    'label' => 'page.review.section.equipment',
                    'icon' => 'lectern',
                    'score' => 4.0,
                ],
                [
                    'label' => 'page.review.section.restauration',
                    'icon' => 'utensils',
                    'score' => 4.5,
                ],
                [
                    'label' => 'page.review.section.accomodation',
                    'icon' => 'bed',
                    'score' => 3.5,
                ],
            ],
            'reviewsTotal' => 64,
        ]);
    }

    #[Route(path: 'portal/reviews/reminder', name: 'provider_portal_review_reminder')]
    public function reminder(): Response
    {
        return $this->render('provider_portal/pages/online/review/reminder.html.twig', [
            'remindersTotal' => 92,
        ]);
    }
}
