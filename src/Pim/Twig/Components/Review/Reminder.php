<?php

namespace App\Pim\Twig\Components\Review;

use App\Pim\Model\ProviderPortal\DTO\Review\ReminderDTO;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Reminder
{
    public ReminderDTO $reminder;
}
