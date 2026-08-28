<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

/**
 * Catalogue les prompts et modèles OpenAI dans /admin/parametres : ils
 * existaient comme défauts d'environnement (services.yaml) mais n'avaient
 * jamais été insérés dans la table parametre — impossibles à surcharger à
 * chaud (seul openai.actif l'était, Version20260825120000). Les prompts
 * dépassent 255 caractères : la colonne valeur passe en LONGTEXT (mapping
 * Doctrine de Types::TEXT) et ils portent le nouveau type « text » (textarea).
 * Tout est inséré à NULL : la variable d'environnement reste la valeur
 * effective.
 */
final class Version20260828130000 extends AbstractMigration
{
    /** @var list<array{0: string, 1: string, 2: string}> nom, type, description */
    private const PARAMETRES = [
        ['openai.reco_prompt', 'text', 'Prompt de la reconnaissance IA des photos (légende, mots-clés du contenu visible, type de vue, intérieur/extérieur). Le format de réponse est imposé par l’appel, pas par le prompt.'],
        ['openai.reco_modele', 'string', 'Modèle OpenAI de la reconnaissance IA des photos.'],
        ['openai.reco_auto_active', 'bool', 'Lance automatiquement la reconnaissance IA à l’import d’une photo (sous réserve d’openai.actif).'],
        ['openai.retouche_prompt', 'text', 'Prompt de la retouche IA des photos (luminosité, contraste, netteté, sans toucher au contenu).'],
        ['openai.retouche_modele', 'string', 'Modèle OpenAI de la retouche IA des photos.'],
        ['openai.suggestion_prompt', 'text', 'Gabarit de la pilule « Suggérer » des descriptions ; conserver les placeholders {contexte}, {champ} et {valeur}.'],
        ['openai.suggestion_modele', 'string', 'Modèle OpenAI de la pilule « Suggérer ».'],
    ];

    public function getDescription(): string
    {
        return 'Catalogue les prompts et modèles OpenAI dans /admin/parametres, colonne valeur élargie en TEXT.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametre CHANGE valeur valeur LONGTEXT DEFAULT NULL');
        foreach (self::PARAMETRES as [$nom, $type, $description]) {
            $this->addSql(
                'INSERT INTO parametre (id, nom, description, type, valeur, updated_at) VALUES (?, ?, ?, ?, NULL, NULL)',
                [(new Ulid())->toBinary(), $nom, $description, $type],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $noms = array_map(static fn (array $parametre): string => "'".$parametre[0]."'", self::PARAMETRES);
        $this->addSql('DELETE FROM parametre WHERE nom IN ('.implode(', ', $noms).')');
        $this->addSql('ALTER TABLE parametre CHANGE valeur valeur VARCHAR(255) DEFAULT NULL');
    }
}
