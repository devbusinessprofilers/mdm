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
    public function __construct(private Connection $connection)
    {
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
            'echecs' => (int) $this->connection->fetchOne(
                "SELECT
                    (SELECT COUNT(*) FROM etl_import_job WHERE status IN ('echoue', 'termine_avec_erreurs'))
                    + (SELECT COUNT(*) FROM enrichment_fiche_translation WHERE status = 'en_erreur')
                    + (SELECT COUNT(*) FROM ocr_document_extraction WHERE status = 'failed')
                    + (SELECT COUNT(*) FROM dam_media_asset WHERE status = 'failed' AND deleted_at IS NULL)
                    + (SELECT COUNT(*) FROM etl_fiche_marketplace WHERE status = 'failed')
                    + (SELECT COUNT(*) FROM outbox_message WHERE status = 'failed')",
            ),
            'ia' => (int) $this->connection->fetchOne(
                'SELECT COUNT(DISTINCT ext.fiche_id)
                 FROM ocr_document_extraction ext
                 INNER JOIN ocr_suggestion sug ON sug.extraction_id = ext.id
                 WHERE sug.status = \'pending\'',
            ),
            'repli' => (int) $this->connection->fetchOne(
                'SELECT COUNT(DISTINCT fiche_id) FROM pim_fiche_affiliation WHERE repli = 1',
            ),
        ];
    }
}
