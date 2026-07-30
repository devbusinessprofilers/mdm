<?php

namespace App\Pim\Controller\ProviderPortal;

use App\Pim\Notification\ProviderPortal\InvitationNotification;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class NotificationController extends AbstractController
{
    public function __construct(
        private readonly InvitationNotification $invitationNotification
    ) {
    }

    #[Route(path: '/portal/invitation', name: 'provider_portal_invitation')]
    public function index()
    {
        $this->invitationNotification->notify('test@businessprofilers.fr');

        return $this->render('provider_portal/emails/invitation.html.twig');
    }
}
