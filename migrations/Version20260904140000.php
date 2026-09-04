<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Pim\Geo\ZonesGeographiques;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Zones d'intervention mobiles (Activité, Service) : les libellés saisis en
 * texte libre (« Île-de-France », « Yvelines ») deviennent les codes du
 * référentiel ZonesGeographiques (FR-IDF, FR-78) ; une fiche à zones sans
 * pays est réputée française. Les libellés non résolus sont abandonnés et
 * comptés dans la sortie de la migration.
 */
final class Version20260904140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Zones mobiles : libellés libres → codes du référentiel pays / régions / départements.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SELECT 1');
    }

    public function postUp(Schema $schema): void
    {
        foreach (['pim_activite', 'pim_service_evenementiel'] as $table) {
            $abandonnes = [];
            $rows = $this->connection->fetchAllAssociative(sprintf('SELECT id, pays_mobiles, regions_mobiles, departements_mobiles FROM %s', $table));
            foreach ($rows as $row) {
                $pays = self::liste($row['pays_mobiles']);
                $regions = self::liste($row['regions_mobiles']);
                $departements = self::liste($row['departements_mobiles']);
                if ([] === $pays && [] === $regions && [] === $departements) {
                    continue;
                }
                // Legacy : « Toute la France » en région = toutes les régions
                // françaises ; un pays saisi en région ou département
                // (« Monaco ») rejoint la liste des pays.
                $touteLaFrance = [] !== array_filter($regions, static fn (string $r): bool => 'toute la france' === mb_strtolower(trim($r)));
                $paysDeguises = [];
                $filtre = static function (array $valeurs) use (&$paysDeguises): array {
                    return array_values(array_filter($valeurs, static function (string $v) use (&$paysDeguises): bool {
                        if ('toute la france' === mb_strtolower(trim($v))) {
                            return false;
                        }
                        if (null === ZonesGeographiques::resoudreRegion($v) && null === ZonesGeographiques::resoudreDepartement($v) && null !== ($code = ZonesGeographiques::resoudrePays($v))) {
                            $paysDeguises[] = $code;

                            return false;
                        }

                        return true;
                    }));
                };
                $regions = $filtre($regions);
                $departements = $filtre($departements);
                $codesPays = self::resoudre([...$pays, ...$paysDeguises], ZonesGeographiques::resoudrePays(...), $abandonnes);
                $codesRegions = self::resoudre($regions, ZonesGeographiques::resoudreRegion(...), $abandonnes);
                $codesDepartements = self::resoudre($departements, ZonesGeographiques::resoudreDepartement(...), $abandonnes);
                if ($touteLaFrance) {
                    $codesRegions = array_values(array_unique([...$codesRegions, ...array_filter(array_values(ZonesGeographiques::regions()), static fn (string $c): bool => str_starts_with($c, 'FR-'))]));
                }
                if (($touteLaFrance || [] !== $codesRegions || [] !== $codesDepartements) && !in_array('FR', $codesPays, true)
                    && [] !== array_filter([...$codesRegions, ...$codesDepartements], static fn (string $c): bool => str_starts_with($c, 'FR-'))) {
                    $codesPays[] = 'FR';
                }
                $this->connection->executeStatement(
                    sprintf('UPDATE %s SET pays_mobiles = :pays, regions_mobiles = :regions, departements_mobiles = :departements WHERE id = :id', $table),
                    [
                        'pays' => json_encode($codesPays, JSON_THROW_ON_ERROR),
                        'regions' => json_encode($codesRegions, JSON_THROW_ON_ERROR),
                        'departements' => json_encode($codesDepartements, JSON_THROW_ON_ERROR),
                        'id' => $row['id'],
                    ],
                );
            }
            if ([] !== $abandonnes) {
                arsort($abandonnes);
                $this->write(sprintf('%s : libellés non résolus abandonnés : %s', $table, implode(', ', array_map(static fn (string $l, int $n): string => sprintf('%s (%d)', $l, $n), array_keys($abandonnes), $abandonnes))));
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SELECT 1');
    }

    /** @return list<string> */
    private static function liste(mixed $json): array
    {
        $valeurs = is_string($json) ? json_decode($json, true) : null;

        return is_array($valeurs) ? array_values(array_filter(array_map(static fn (mixed $v): string => trim((string) $v), $valeurs), static fn (string $v): bool => '' !== $v)) : [];
    }

    /**
     * @param list<string>                     $valeurs
     * @param callable(string): ?string        $resolveur
     * @param array<string, int>               $abandonnes
     *
     * @return list<string>
     */
    private static function resoudre(array $valeurs, callable $resolveur, array &$abandonnes): array
    {
        $codes = [];
        foreach ($valeurs as $valeur) {
            $code = $resolveur($valeur);
            if (null === $code) {
                $abandonnes[$valeur] = ($abandonnes[$valeur] ?? 0) + 1;
                continue;
            }
            if (!in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
