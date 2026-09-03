<?php

declare(strict_types=1);

namespace App\Dashboard\Repository;

use Doctrine\DBAL\Connection;

/**
 * Volumes des files de travail du tableau de bord : ce qui attend une action
 * humaine, compté en direct (pas de snapshot — ces chiffres doivent être
 * exacts au moment où l'on clique).
 */
final readonly class FilesATraiterRepository
{
    public function __construct(
        private Connection $connection,
        private JournalTraitementsRepository $journal,
    ) {
    }

    /** @return array{a_valider: int, a_publier: int, echecs: int, ia: int, repli: int} */
    public function comptes(): array
    {
        return [
            'a_valider' => (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM pim_fiche WHERE status = 'en_attente_validation'",
            ),
            'a_publier' => (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM pim_fiche WHERE status = 'validee'",
            ),
            // Même périmètre que la vue /admin/traitements-en-echec : une seule
            // définition des échecs par famille, dans le journal.
            'echecs' => $this->journal->compterEchecs(),
            // Tout ce qui attend un arbitrage humain : suggestions IA (OCR)
            // ET écarts d'adresse (BAN / Geoapify).
            'ia' => (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM pim_fiche f
                 WHERE EXISTS (
                        SELECT 1 FROM ocr_document_extraction ext
                        INNER JOIN ocr_suggestion sug ON sug.extraction_id = ext.id
                        WHERE ext.fiche_id = f.id AND sug.status = 'pending')
                    OR EXISTS (
                        SELECT 1 FROM pim_localisation loc
                        WHERE loc.id = f.localisation_id AND loc.ban_ecart = 1)",
            ),
            // Fiches sans aucun contact — même définition que le filtre
            // « sans_contact » du référentiel, où la carte atterrit.
            'repli' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM pim_fiche f
                 WHERE NOT EXISTS (SELECT 1 FROM pim_fiche_affiliation aff WHERE aff.fiche_id = f.id)',
            ),
        ];
    }
}
