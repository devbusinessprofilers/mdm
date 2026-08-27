<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Etl\Enum\SalesforceCsvInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Envoie à Salesforce un CSV en pièce jointe d'un e-mail, en reprenant à
 * l'identique le format de l'ancienne intégration extranet : l'objet
 * `integration=<jeton>;interface=Produits|Salles` et le nom de fichier
 * attendus par le connecteur Salesforce (email → intégration).
 *
 * Tant que la synchro n'est pas configurée (drapeau désactivé ou destinataire
 * vide), l'envoi est journalisé puis ignoré : rien ne part en dev/test ni
 * avant le « go » de mise en production.
 */
#[WithMonologChannel('salesforce')]
final readonly class SalesforceCsvMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private SalesforceCsvSettings $settings,
        private LoggerInterface $logger,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->settings->isConfigured();
    }

    /**
     * @param string $csv CSV complet (en-tête + lignes), encodage UTF-8
     */
    public function envoyer(SalesforceCsvInterface $interface, string $csv): void
    {
        if (!$this->settings->isConfigured()) {
            $this->logger->info('Synchro Salesforce CSV désactivée : e-mail {interface} ignoré.', [
                'interface' => $interface->value,
            ]);

            return;
        }

        $expediteur = $this->settings->expediteur();
        $destinataire = $this->settings->destinataire();
        if ('' === $expediteur || '' === $destinataire) {
            $this->logger->warning('Envoi Salesforce {interface} ignoré : expéditeur ou destinataire vide.', [
                'interface' => $interface->value,
            ]);

            return;
        }

        $email = (new Email())
            ->from($expediteur)
            ->to($destinataire)
            ->subject(sprintf('integration=%s;interface=%s', $this->settings->jetonIntegration(), $interface->value))
            ->text('')
            ->attach($csv, $interface->nomFichier(), 'text/csv');

        $this->mailer->send($email);
    }
}
