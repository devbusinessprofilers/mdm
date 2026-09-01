<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Lov\LieuLovCatalog;

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
    public const SEUIL_RAPPROCHEMENT = NomSimilarite::SEUIL_DEFAUT;

    /** Pénalité de score quand le rapprochement n'a abouti qu'en repli France entière (sans code postal). */
    private const PENALITE_SANS_CODE_POSTAL = 0.9;

    /**
     * Code NAF → typologie de lieu, pour un backfill grossier quand la fiche
     * n'en a pas. Table volontairement courte : seuls les NAF sans ambiguïté —
     * 55.10Z (hôtels, codes indexés par étoiles) et 55.20Z (hébergements de
     * courte durée, trop large) sont exclus.
     */
    private const NAF_TYPOLOGIE = [
        '55.30Z' => 'GENERALE_TYPOLOGIE_33', // terrains de camping
        '92.00Z' => 'GENERALE_TYPOLOGIE_18', // jeux de hasard → casino
        '91.02Z' => 'GENERALE_TYPOLOGIE_26', // musées
        '93.21Z' => 'GENERALE_TYPOLOGIE_27', // parcs d'attractions
        '59.14Z' => 'GENERALE_TYPOLOGIE_36', // projection de films → cinéma
        '90.04Z' => 'GENERALE_TYPOLOGIE_31', // gestion de salles de spectacles
        '93.11Z' => 'GENERALE_TYPOLOGIE_30', // gestion d'installations sportives
    ];

    public function __construct(private RechercheEntrepriseClient $client)
    {
    }

    /**
     * @return list<SuggestionProposee>
     *
     * @throws EnrichissementIndisponibleException quand Sirene est en panne ou
     *                                             sous quota — à distinguer
     *                                             d'un « aucun constat »
     */
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
        return [
            ...(null === ($tva = $this->propositionTva($lieu, $info)) ? [] : [$tva]),
            ...$this->propositionsLegales($lieu, $info, null),
            ...(null === ($typologie = $this->propositionTypologie($lieu, $info, null)) ? [] : [$typologie]),
        ];
    }

    /** @return list<SuggestionProposee> */
    private function analyserSansSiret(Lieu $lieu, ?string $codePostal): array
    {
        $label = (string) $lieu->fiche()->label();
        if ('' === trim($label)) {
            return [];
        }
        $info = $this->client->findBest($label, $codePostal, absorberIndisponibilite: false);
        if (null === $info || null === $info->siret) {
            return [];
        }
        $score = NomSimilarite::score($label, $info->denomination ?? $info->raisonSociale ?? '');
        // Rapprochement obtenu sans l'ancrage du code postal : risque d'homonyme
        // d'une autre commune — score pénalisé (écarte les cas limites, et la
        // confiance affichée à l'arbitre le signale).
        if ($info->rapprochementSansCodePostal) {
            $score *= self::PENALITE_SANS_CODE_POSTAL;
        }
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

        return [
            ...$propositions,
            ...$this->propositionsLegales($lieu, $info, $score),
            ...(null === ($typologie = $this->propositionTypologie($lieu, $info, $score)) ? [] : [$typologie]),
        ];
    }

    /**
     * Backfill grossier de la typologie depuis le code NAF — uniquement quand
     * la fiche n'en a aucune ; score plafonné pour signaler l'approximation.
     */
    private function propositionTypologie(Lieu $lieu, EntrepriseInfo $info, ?float $score): ?SuggestionProposee
    {
        if (null === $info->naf || [] !== $lieu->generaleTypologie()) {
            return null;
        }
        $code = self::NAF_TYPOLOGIE[$info->naf] ?? null;
        $code = null === $code ? null : LovValeurResolution::codePour(LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE'), $code);
        if (null === $code) {
            return null;
        }

        return new SuggestionProposee(
            action: SuggestionAction::RemplirChamp,
            champ: 'lieu_lov_typologie',
            label: 'Typologie',
            valeurActuelle: null,
            valeurProposee: LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE')[$code] ?? $code,
            score: min(0.5, $score ?? 0.5),
            payload: ['attribut' => 'GENERALE_TYPOLOGIE', 'codes' => [$code]],
        );
    }

    /**
     * Backfill des champs légaux déductibles de l'annuaire : forme juridique
     * (libellé INSEE) et raison sociale — uniquement quand le champ est vide.
     *
     * @return list<SuggestionProposee>
     */
    private function propositionsLegales(Lieu $lieu, EntrepriseInfo $info, ?float $score): array
    {
        $propositions = [];
        if (null !== $info->formeJuridique && null === $lieu->administratif()->infoLegaleFormeJuridique()) {
            $propositions[] = new SuggestionProposee(
                action: SuggestionAction::RemplirChamp,
                champ: 'info_legale_forme_juridique',
                label: 'Forme juridique',
                valeurActuelle: null,
                valeurProposee: $info->formeJuridique,
                score: $score,
            );
        }
        if (null !== $info->raisonSociale && null === $lieu->administratif()->infoLegaleNom()) {
            $propositions[] = new SuggestionProposee(
                action: SuggestionAction::RemplirChamp,
                champ: 'info_legale_nom',
                label: 'Raison sociale',
                valeurActuelle: null,
                valeurProposee: $info->raisonSociale,
                score: $score,
            );
        }

        return $propositions;
    }

    /**
     * N° de TVA déduit du SIREN (calcul exact) : proposé quand le champ est
     * vide, et aussi quand la valeur saisie DIFFÈRE — la TVA se calcule, une
     * divergence est une erreur de saisie à signaler, pas une variante.
     */
    private function propositionTva(Lieu $lieu, EntrepriseInfo $info): ?SuggestionProposee
    {
        $actuelle = $lieu->administratif()->infoLegaleNumTva();
        if (null === $info->numeroTva || strtoupper(trim((string) $actuelle)) === $info->numeroTva) {
            return null;
        }

        return new SuggestionProposee(
            action: SuggestionAction::RemplirChamp,
            champ: 'info_legale_num_tva',
            label: 'N° TVA intracommunautaire',
            valeurActuelle: $actuelle,
            valeurProposee: $info->numeroTva,
            score: null,
        );
    }

    private static function siretNormalise(?string $siret): ?string
    {
        $siret = preg_replace('/\D/', '', (string) $siret) ?? '';

        return preg_match('/^\d{14}$/', $siret) ? $siret : null;
    }
}
