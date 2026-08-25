<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Shared\Service\ParametreProviderInterface;

/**
 * Réglages de la synchronisation Salesforce par CSV e-mail (système de
 * transition en attendant l'API Salesforce). Lus à l'usage via les paramètres
 * applicatifs (surchargeables dans /admin), jamais au constructeur.
 *
 * La garde `isConfigured()` protège tout envoi : désactivée par défaut, elle
 * neutralise la synchro en dev/test, pendant l'import legacy et tant que la
 * migration prod n'a pas reçu le « go » (drapeau + destinataire vides).
 */
final readonly class SalesforceCsvSettings
{
    public function __construct(
        private ParametreProviderInterface $parametres,
        private string $expediteurParDefaut,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->parametres->bool('salesforce.csv_actif')
            && '' !== $this->destinataire()
            && '' !== $this->jetonIntegration();
    }

    public function destinataire(): string
    {
        return trim($this->parametres->string('salesforce.csv_destinataire'));
    }

    public function expediteur(): string
    {
        $expediteur = trim($this->parametres->string('salesforce.csv_expediteur'));

        return '' === $expediteur ? $this->expediteurParDefaut : $expediteur;
    }

    /**
     * Jeton d'intégration Salesforce présent dans l'objet des e-mails
     * (`integration=<jeton>;interface=…`). Spécifique à l'org Salesforce cible.
     */
    public function jetonIntegration(): string
    {
        return trim($this->parametres->string('salesforce.csv_token'));
    }
}
