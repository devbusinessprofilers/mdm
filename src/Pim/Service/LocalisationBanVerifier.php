<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Localisation;

/**
 * Logique commune de vérification d'une adresse française contre la BAN,
 * partagée par la commande batch (app:localisation:verifier) et la
 * vérification au fil de l'eau (message VerifierAdresseFiche) :
 * classement en panier, enrichissements sûrs et trace sur la localisation.
 *
 * Rien de destructif : une rue ou une ville réellement différente n'est
 * jamais écrasée, elle est marquée « écart » pour l'arbitrage humain dans
 * l'écran Qualité.
 */
final class LocalisationBanVerifier
{
    public const SEUIL_DEFAUT = 0.85;
    public const SCORE_DOUTEUX = 0.4;

    /** @param array{score: ?float, label: ?string, name: ?string, codePostal: ?string, ville: ?string, latitude: ?string, longitude: ?string, type: ?string}|null $resultat */
    public static function panier(Localisation $localisation, ?array $resultat, float $seuil): string
    {
        $score = $resultat['score'] ?? null;
        if (null === $resultat || null === $score || $score < self::SCORE_DOUTEUX) {
            return 'douteuse';
        }
        if ($score < $seuil || !self::concordant($localisation, $resultat)) {
            return $score >= $seuil ? 'correction' : 'douteuse';
        }

        return null === $localisation->latitude() && null !== $resultat['latitude'] ? 'enrichissable' : 'conforme';
    }

    /**
     * Enrichissements sûrs uniquement (adresse concordante au-dessus du
     * seuil) : GPS manquants, CP/ville vides, recasage d'une ville identique
     * aux accents près. Retourne vrai si la fiche a été modifiée.
     *
     * @param array{score: ?float, label: ?string, name: ?string, codePostal: ?string, ville: ?string, latitude: ?string, longitude: ?string, type: ?string}|null $resultat
     */
    public static function appliquerEnrichissements(Localisation $localisation, ?array $resultat, float $seuil): bool
    {
        if (null === $resultat || 'douteuse' === self::panier($localisation, $resultat, $seuil)
            || 'correction' === self::panier($localisation, $resultat, $seuil)) {
            return false;
        }
        $modifie = false;
        if (null === $localisation->latitude() && null !== $resultat['latitude'] && null !== $resultat['longitude']) {
            try {
                $localisation->changeLatitude($resultat['latitude']);
                $localisation->changeLongitude($resultat['longitude']);
                $modifie = true;
            } catch (\InvalidArgumentException) {
                // Coordonnée BAN inattendue : on n'écrit rien.
            }
        }
        if (null === $localisation->codePostal() && null !== $resultat['codePostal']) {
            $localisation->changeCodePostal($resultat['codePostal']);
            $modifie = true;
        }
        if (null === $localisation->ville() && null !== $resultat['ville']) {
            $localisation->changeVille($resultat['ville']);
            $modifie = true;
        }
        if (null !== $localisation->ville() && null !== $resultat['ville']
            && $localisation->ville() !== $resultat['ville']
            && ReferentielGeographiqueFrancais::cle($localisation->ville()) === ReferentielGeographiqueFrancais::cle($resultat['ville'])) {
            $localisation->changeVille($resultat['ville']);
            $modifie = true;
        }

        return $modifie;
    }

    /**
     * Trace du passage : score, date, empreinte, et — en cas d'écart —
     * la proposition BAN pour l'arbitrage dans l'écran Qualité.
     *
     * @param array{score: ?float, label: ?string, name: ?string, codePostal: ?string, ville: ?string, latitude: ?string, longitude: ?string, type: ?string}|null $resultat
     */
    public static function tracer(Localisation $localisation, ?array $resultat, float $seuil): void
    {
        $panier = self::panier($localisation, $resultat, $seuil);
        $ecart = in_array($panier, ['douteuse', 'correction'], true);
        $localisation->recordBanVerification(
            $resultat['score'] ?? null,
            $ecart && null !== $resultat ? [
                'label' => $resultat['label'],
                // La voie seule et le niveau du résultat (housenumber, street,
                // locality, municipality) : l'acceptation en un clic ne
                // réécrit la rue qu'au niveau rue/numéro.
                'name' => $resultat['name'],
                'type' => $resultat['type'],
                'codePostal' => $resultat['codePostal'],
                'ville' => $resultat['ville'],
                'latitude' => $resultat['latitude'],
                'longitude' => $resultat['longitude'],
            ] : null,
            $ecart,
        );
    }

    /**
     * Ligne à soumettre au lot BAN, ou null si l'adresse n'est pas vérifiable.
     *
     * @return array{id: string, adresse: string, codePostal: string, ville: string}|null
     */
    public static function ligne(Fiche $fiche): ?array
    {
        $localisation = $fiche->localisation();
        if (null === $localisation || !self::estFrancaise($localisation)
            || (null === $localisation->ruePostale() && null === $localisation->ville())) {
            return null;
        }

        return [
            'id' => (string) $fiche->code(),
            'adresse' => trim(sprintf('%s %s', $localisation->ruePostale() ?? '', $localisation->ville() ?? '')),
            'codePostal' => $localisation->codePostal() ?? '',
            'ville' => $localisation->ville() ?? '',
        ];
    }

    public static function estFrancaise(Localisation $localisation): bool
    {
        return 'FR' === $localisation->countryCode()
            || (null !== $localisation->pays() && 'france' === ReferentielGeographiqueFrancais::cle($localisation->pays()));
    }

    /** @param array{score: ?float, label: ?string, name: ?string, codePostal: ?string, ville: ?string, latitude: ?string, longitude: ?string, type: ?string} $resultat */
    private static function concordant(Localisation $localisation, array $resultat): bool
    {
        $cpConcorde = null === $resultat['codePostal'] || null === $localisation->codePostal()
            || $resultat['codePostal'] === $localisation->codePostal();
        $villeConcorde = null === $resultat['ville'] || null === $localisation->ville()
            || ReferentielGeographiqueFrancais::cle($resultat['ville']) === ReferentielGeographiqueFrancais::cle($localisation->ville());

        return $cpConcorde && $villeConcorde;
    }
}
