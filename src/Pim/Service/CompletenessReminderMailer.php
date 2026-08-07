<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\FicheCollaborateur;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;

/**
 * Emails de relance de complétude aux collaborateurs prestataires : texte
 * brut construit en code (même pattern que les invitations de comptes),
 * français par défaut, anglais pour les collaborateurs non francophones.
 */
final readonly class CompletenessReminderMailer
{
    public function __construct(
        #[Autowire(env: 'MAILER_FROM')]
        private string $sender,
    ) {
    }

    public function build(FicheCollaborateur $collaborateur, Fiche $fiche, int $score): Email
    {
        $label = $fiche->label() ?: sprintf('Fiche %d', $fiche->code());
        $french = !str_starts_with($collaborateur->language(), 'en');
        $greeting = '' !== $collaborateur->firstName()
            ? ($french ? 'Bonjour '.$collaborateur->firstName() : 'Hello '.$collaborateur->firstName())
            : ($french ? 'Bonjour' : 'Hello');

        if ($french) {
            $subject = sprintf('[MDM] Votre fiche « %s » est complétée à %d %%', $label, $score);
            $body = $greeting.",\n\n"
                .sprintf("Votre fiche « %s » est complétée à %d %%.\n", $label, $score)
                ."Plus une fiche est complète, plus elle est visible et attractive pour vos clients.\n\n"
                ."Merci de compléter les informations manquantes ou de contacter votre interlocuteur Business Profilers.\n\n"
                ."L'équipe Business Profilers";
        } else {
            $subject = sprintf('[MDM] Your listing "%s" is %d%% complete', $label, $score);
            $body = $greeting.",\n\n"
                .sprintf("Your listing \"%s\" is %d%% complete.\n", $label, $score)
                ."The more complete a listing is, the more visible and attractive it becomes for your clients.\n\n"
                ."Please fill in the missing information or contact your Business Profilers representative.\n\n"
                .'The Business Profilers team';
        }

        return (new Email())
            ->from($this->sender)
            ->to($collaborateur->email())
            ->subject($subject)
            ->text($body);
    }
}
