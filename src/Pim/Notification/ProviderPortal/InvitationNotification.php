<?php

namespace App\Pim\Notification\ProviderPortal;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class InvitationNotification
{
    private $mailer;
    private $templating;

    private $params;

    public function __construct(MailerInterface $mailer, Environment $templating, ParameterBagInterface $params)
    {
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->params = $params;
    }

    public function notify($email)
    {
        $message = (new Email())
            ->from('Business Profilers <'.$this->params->get('default_from_email').'>')
            ->to($email)
            ->bcc('Portail BP - Email système <emailsysteme_portailbp@businessprofilers.fr>')
            ->subject('Invitation à rejoindre le Portail Business Profilers')
            ->html(
                $this->templating->render('provider_portal/emails/invitation.html.twig')
            );

        return $this->mailer->send($message);
    }
}
