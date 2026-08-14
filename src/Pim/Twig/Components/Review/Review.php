<?php

namespace App\Pim\Twig\Components\Review;

use App\Pim\Model\ProviderPortal\DTO\Review\ReviewDTO;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Review', template: 'pim/components/Review/Review.html.twig')]
class Review
{
    public ReviewDTO $review;
}
