<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Localisation;

/**
 * Aiguillage des vérifications d'adresse par pays : la BAN pour la France,
 * Geoapify pour l'étranger (code ISO requis). Un client non configuré rend
 * l'adresse non vérifiable — la chaîne (IndexFiche → VerifierAdresseFiche)
 * n'enfile alors rien.
 */
final readonly class GeocodeurAdresses
{
    public function __construct(
        private BanClientInterface $ban,
        private GeocodeurEtrangerInterface $etranger,
    ) {
    }

    public function clientPour(Localisation $localisation): ?GeocodeurAdresseInterface
    {
        if (LocalisationBanVerifier::estFrancaise($localisation)) {
            return $this->ban->isConfigured() ? $this->ban : null;
        }
        if (null !== $localisation->countryCode() && $this->etranger->isConfigured()) {
            return $this->etranger;
        }

        return null;
    }

    /**
     * Ligne à soumettre au vérificateur du pays, ou null si l'adresse n'est
     * pas vérifiable (pas de localisation, pays inconnu, adresse vide).
     *
     * @return array{id: string, adresse: string, codePostal: string, ville: string, pays?: string}|null
     */
    public function ligne(Fiche $fiche): ?array
    {
        $localisation = $fiche->localisation();
        if (null === $localisation) {
            return null;
        }
        if (LocalisationBanVerifier::estFrancaise($localisation)) {
            return LocalisationBanVerifier::ligne($fiche);
        }
        if (null === $localisation->countryCode()
            || (null === $localisation->ruePostale() && null === $localisation->ville())) {
            return null;
        }

        return [
            'id' => (string) $fiche->code(),
            'adresse' => $localisation->ruePostale() ?? '',
            'codePostal' => $localisation->codePostal() ?? '',
            'ville' => $localisation->ville() ?? '',
            'pays' => $localisation->countryCode(),
        ];
    }

    /**
     * Une vérification a-t-elle un sens pour cette adresse : un client
     * configuré la couvre ET elle a changé depuis le dernier passage
     * (empreintes différentes, ou jamais vérifiée).
     */
    public function estVerifiable(Localisation $localisation): bool
    {
        return null !== $localisation->addressFingerprint()
            && $localisation->addressFingerprint() !== $localisation->banFingerprint()
            && null !== $this->clientPour($localisation);
    }
}
