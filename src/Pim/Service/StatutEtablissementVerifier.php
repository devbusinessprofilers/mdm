<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\SuggestionAction;

/**
 * Confronte un lieu à l'annuaire des entreprises (Sirene) pour produire des
 * constats à arbitrer, sans rien persister :
 *
 *  - avec un SIRET stocké : contrôle exact de l'état administratif (établissement
 *    cessé → proposition d'archivage) et complément du n° de TVA manquant ;
 *  - sans SIRET : rapprochement par nom + code postal ; si la dénomination
 *    correspond assez, proposition de backfill du SIRET (et de la TVA).
 *
 * France uniquement (Sirene) ; hors de France, aucun constat. Les propositions
 * sont volontairement conservatrices : elles restent des suggestions arbitrées.
 */
final readonly class StatutEtablissementVerifier
{
    /** Similarité minimale nom PIM ↔ dénomination Sirene pour proposer un backfill sans SIRET stocké. */
    public const SEUIL_RAPPROCHEMENT = 0.82;

    public function __construct(private RechercheEntrepriseClient $client)
    {
    }

    /** @return list<SuggestionProposee> */
    public function analyser(Lieu $lieu): array
    {
        $localisation = $lieu->localisation();
        if (null === $localisation || !LocalisationBanVerifier::estFrancaise($localisation)) {
            return [];
        }
        $siret = self::siretNormalise($lieu->administratif()->infoLegaleSiret());

        return null !== $siret
            ? $this->analyserAvecSiret($lieu, $siret)
            : $this->analyserSansSiret($lieu, $localisation->codePostal());
    }

    /** @return list<SuggestionProposee> */
    private function analyserAvecSiret(Lieu $lieu, string $siret): array
    {
        $info = $this->client->findStatut($siret);
        if (null === $info) {
            return [];
        }
        // Établissement cessé : l'archivage prime, inutile de compléter la TVA
        // d'une fiche qui va être retirée.
        if ($info->estCesse()) {
            return [new SuggestionProposee(
                action: SuggestionAction::Archiver,
                champ: 'statut',
                label: 'Établissement fermé',
                valeurActuelle: 'Fiche active (SIRET '.$siret.')',
                valeurProposee: 'Établissement cessé selon l\'annuaire des entreprises — archiver ?',
                score: 1.0,
            )];
        }
        $tva = $this->propositionTva($lieu, $info);

        return null === $tva ? [] : [$tva];
    }

    /** @return list<SuggestionProposee> */
    private function analyserSansSiret(Lieu $lieu, ?string $codePostal): array
    {
        $label = (string) $lieu->fiche()->label();
        if ('' === trim($label)) {
            return [];
        }
        $info = $this->client->findBest($label, $codePostal);
        if (null === $info || null === $info->siret) {
            return [];
        }
        $score = self::similarite($label, $info->denomination ?? $info->raisonSociale ?? '');
        if ($score < self::SEUIL_RAPPROCHEMENT) {
            return [];
        }
        $propositions = [new SuggestionProposee(
            action: SuggestionAction::RemplirChamp,
            champ: 'info_legale_siret',
            label: 'SIRET',
            valeurActuelle: null,
            valeurProposee: $info->siret,
            score: $score,
        )];
        $tva = $this->propositionTva($lieu, $info);
        if (null !== $tva) {
            $propositions[] = $tva;
        }

        return $propositions;
    }

    /** Complète le n° de TVA uniquement s'il est absent de la fiche et déductible du SIREN. */
    private function propositionTva(Lieu $lieu, EntrepriseInfo $info): ?SuggestionProposee
    {
        if (null === $info->numeroTva || null !== $lieu->administratif()->infoLegaleNumTva()) {
            return null;
        }

        return new SuggestionProposee(
            action: SuggestionAction::RemplirChamp,
            champ: 'info_legale_num_tva',
            label: 'N° TVA intracommunautaire',
            valeurActuelle: null,
            valeurProposee: $info->numeroTva,
            score: null,
        );
    }

    private static function siretNormalise(?string $siret): ?string
    {
        $siret = preg_replace('/\D/', '', (string) $siret) ?? '';

        return preg_match('/^\d{14}$/', $siret) ? $siret : null;
    }

    private static function similarite(string $a, string $b): float
    {
        $a = mb_strtolower(trim($a));
        $b = mb_strtolower(trim($b));
        if ('' === $a || '' === $b) {
            return 0.0;
        }
        similar_text($a, $b, $pourcent);

        return $pourcent / 100;
    }
}
