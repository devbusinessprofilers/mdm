<?php

declare(strict_types=1);

namespace App\Dashboard\Journal;

/**
 * État d'un traitement de fond tel que le journal l'affiche, quel que soit
 * le vocabulaire de la table de suivi d'origine : les dix familles gardent
 * leurs colonnes et leurs enums, le journal ne parle qu'en ces états-là.
 */
enum EtatTraitement: string
{
    case EnFile = 'en_file';
    case EnCours = 'en_cours';
    case Termine = 'termine';
    case TermineAvecErreurs = 'termine_avec_erreurs';
    case Echoue = 'echoue';
    case Retire = 'retire';
    case Expire = 'expire';
    case Obsolete = 'obsolete';
    case Inconnu = 'inconnu';

    /** Vocabulaires des tables de suivi (imports, OCR, traductions, médias, exports, marketplace, outbox…) ramenés à un état. */
    private const PAR_STATUT = [
        'en_attente' => self::EnFile,
        'pending' => self::EnFile,
        'uploaded' => self::EnFile,
        'queued' => self::EnFile,
        'en_cours' => self::EnCours,
        'processing' => self::EnCours,
        'running' => self::EnCours,
        'partially_reviewed' => self::EnCours,
        'termine' => self::Termine,
        'terminee' => self::Termine,
        'ready' => self::Termine,
        'reviewed' => self::Termine,
        'disponible' => self::Termine,
        'processed' => self::Termine,
        'synced' => self::Termine,
        'published' => self::Termine,
        'accepted' => self::Termine,
        'envoyee' => self::Termine,
        'planifiee' => self::EnFile,
        'termine_avec_erreurs' => self::TermineAvecErreurs,
        'echoue' => self::Echoue,
        'failed' => self::Echoue,
        'en_erreur' => self::Echoue,
        'removed' => self::Retire,
        'deleting' => self::Retire,
        'deleted' => self::Retire,
        'rejected' => self::Retire,
        'exclue' => self::Retire,
        'ignoree' => self::Retire,
        'annulee' => self::Retire,
        'expiree' => self::Expire,
        'obsolete' => self::Obsolete,
    ];

    public static function depuisStatut(string $statut): self
    {
        return self::PAR_STATUT[$statut] ?? self::Inconnu;
    }

    public function libelle(): string
    {
        return match ($this) {
            self::EnFile => 'En file',
            self::EnCours => 'En cours',
            self::Termine => 'Terminé',
            self::TermineAvecErreurs => 'Terminé avec erreurs',
            self::Echoue => 'Échoué',
            self::Retire => 'Retiré',
            self::Expire => 'Expiré',
            self::Obsolete => 'Obsolète',
            self::Inconnu => 'Inconnu',
        };
    }

    /** Classe de fond du jeton dans les écrans du journal. */
    public function teinte(): string
    {
        return match ($this) {
            self::EnFile, self::Retire, self::Expire, self::Obsolete, self::Inconnu => 'bg-neutral-200',
            self::EnCours => 'bg-primary-4',
            self::Termine => 'bg-success-pastel',
            self::TermineAvecErreurs => 'bg-peach-pastel',
            self::Echoue => 'bg-error-pastel',
        };
    }

    /** Vrai pour ce que le tableau de bord compte comme « traitement en échec ». */
    public function estEchec(): bool
    {
        return self::Echoue === $this || self::TermineAvecErreurs === $this;
    }
}
