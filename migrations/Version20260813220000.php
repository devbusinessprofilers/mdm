<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le référentiel des sites de diffusion était un catalogue provisoire issu
 * de la maquette (Bedouk, Kactus, Cvent…). La liste métier réelle est celle
 * de la colonne « Attribution visibilité » de l'export production
 * `listes_fiches_produits_*` : purge des sites de démonstration (leurs
 * sélections de fiches suivent par cascade), le catalogue réel est seedé
 * par app:sites-diffusion:sync. Le site `marketplace_bp` est conservé :
 * c'est « Business Profilers », son code déclenche la sync marketplace.
 */
final class Version20260813220000 extends AbstractMigration
{
    private const DEMO_CODES = [
        'BUSINESS_PROFILERS', 'BP_LIEUX', 'BP_SEMINAIRES', 'BP_EVENEMENTS',
        'BEDOUK', 'ABC_SALLES', '1001_SALLES', 'SEMINAIRE_COM', 'KACTUS',
        'EVENTMAKER', 'MEETINGPACKAGE', 'CVENT', 'VENUEDIRECTORY',
        'FIGARO_EVENEMENTS', 'ECHOS_LE_PARISIEN', 'CHALLENGES', 'STRATEGIES',
        'VOYAGES_AFFAIRES', 'DEPLACEMENTS_PROS', 'ECHO_TOURISTIQUE',
        'TENDANCE_HOTELLERIE', 'TRIVAGO_BUSINESS', 'BOOKING_MEETINGS',
        'HRS_MEETINGS', 'CITUATION', 'VENUU', 'TAGVENUE', 'VENUESCANNER',
        'SPACEBASE', 'EVENTUP', 'HIRE_SPACE',
    ];

    public function getDescription(): string
    {
        return 'Purge les sites de diffusion de démonstration (liste métier réelle via app:sites-diffusion:sync).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(sprintf(
            "DELETE FROM pim_site_diffusion WHERE code IN (%s)",
            implode(', ', array_map(static fn (string $code): string => "'".$code."'", self::DEMO_CODES)),
        ));
    }

    public function down(Schema $schema): void
    {
        // Purge de données de démonstration : pas de retour arrière.
    }
}
