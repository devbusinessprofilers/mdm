<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

/**
 * Catalogue dans /admin/parametres les réglages des nouvelles suggestions
 * d'enrichissement (lots 2026-09-01). La gate Atout France est insérée ACTIVE,
 * comme les autres sources gratuites l'avaient été le 2026-08-25 (référentiel
 * local, aucun coût ni appel réseau) : seul openai reste piloté par sa gate
 * existante, coupée au moment de la livraison. Le prompt des atouts est
 * catalogué au défaut d'environnement, surchargeable à chaud.
 */
final class Version20260901150000 extends AbstractMigration
{
    /** @var list<array{0: string, 1: string, 2: string, 3: ?string}> nom, type, description, valeur insérée (null = défaut env) */
    private const PARAMETRES = [
        ['atout_france.classement_actif', 'bool', 'Active les suggestions du classement officiel Atout France des lieux : étoiles (typologie), nombre de chambres (bouton « Enrichir ce qui manque »).', '1'],
        ['openai.atouts_prompt', 'text', 'Gabarit du prompt de suggestion des atouts ({contexte}, {description}, {max}, {longueur_max}).', null],
    ];

    public function getDescription(): string
    {
        return 'Catalogue la gate Atout France (active) et le prompt des atouts IA dans /admin/parametres.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::PARAMETRES as [$nom, $type, $description, $valeur]) {
            $this->addSql(
                'INSERT INTO parametre (id, nom, description, type, valeur, updated_at) VALUES (?, ?, ?, ?, ?, NULL)',
                [(new Ulid())->toBinary(), $nom, $description, $type, $valeur],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $noms = array_map(static fn (array $parametre): string => "'".$parametre[0]."'", self::PARAMETRES);
        $this->addSql('DELETE FROM parametre WHERE nom IN ('.implode(', ', $noms).')');
    }
}
