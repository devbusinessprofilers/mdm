<?php

declare(strict_types=1);

namespace App\Shared\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Regénère assets/sprites/icons.svg à partir des jeux d'icônes d'ux_icons.
 *
 * Le sprite remplace l'inline systématique d'ux_icon() : la macro d'icône rend
 * un <use> vers un fragment du sprite, le fichier est téléchargé une fois puis
 * servi du cache navigateur — une fiche Lieu embarquait ~300 Ko de SVG répétés.
 *
 * Les <symbol> ne portent que le viewBox : les autres attributs racine des
 * fichiers (fill, width, height) étaient déjà écrasés par les défauts
 * d'ux_icons, repris tels quels par la macro. Les ids internes (masques,
 * clips, dégradés) sont préfixés par le nom du symbole pour éviter toute
 * collision dans le document unique.
 *
 * À relancer après tout ajout ou retrait d'icône, avant asset-map:compile.
 */
#[AsCommand(name: 'app:icons:build-sprite', description: 'Regénère le sprite SVG des icônes du design-system (assets/sprites/icons.svg).')]
final class BuildIconSpriteCommand extends Command
{
    /**
     * Jeux et alias : miroir de config/packages/ux_icons.yaml et des préfixes
     * calculés par templates/pim/components/_icon_markup.html.twig.
     */
    private const SETS = [
        '' => '',
        'stroke' => 'stroke',
        'pin' => 'pin',
        'flags' => 'flags',
        'styled' => 'styled',
    ];

    private const ALIASES = ['caret-down', 'caret-up', 'check', 'eye', 'phone'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $iconsDir = $this->projectDir.'/assets/icons';
        $symbols = [];

        foreach (self::SETS as $sousDossier => $segment) {
            $dossier = '' === $sousDossier ? $iconsDir : $iconsDir.'/'.$sousDossier;
            $fichiers = glob($dossier.'/*.svg') ?: [];
            sort($fichiers);
            foreach ($fichiers as $fichier) {
                $nom = basename($fichier, '.svg');
                $id = 'pp-'.('' === $segment ? '' : $segment.'-').$nom;
                $symbols[$id] = $this->symbol($id, $fichier);
            }
        }

        // Les alias du jeu principal pointent vers les imports Iconify (ph/).
        foreach (self::ALIASES as $nom) {
            $id = 'pp-'.$nom;
            $symbols[$id] = $this->symbol($id, $iconsDir.'/ph/'.$nom.'.svg');
        }

        ksort($symbols);
        $sprite = '<svg xmlns="http://www.w3.org/2000/svg">'."\n".implode("\n", $symbols)."\n".'</svg>'."\n";

        $destination = $this->projectDir.'/assets/sprites/icons.svg';
        if (!is_dir(\dirname($destination))) {
            mkdir(\dirname($destination), 0775, true);
        }
        file_put_contents($destination, $sprite);

        $io->success(sprintf('%d symboles écrits dans %s (%d Ko).', count($symbols), $destination, (int) (strlen($sprite) / 1024)));

        return Command::SUCCESS;
    }

    private function symbol(string $id, string $fichier): string
    {
        $document = new \DOMDocument();
        if (!$document->load($fichier)) {
            throw new \RuntimeException(sprintf('Icône illisible : %s', $fichier));
        }

        $racine = $document->documentElement;
        if (null === $racine || 'svg' !== $racine->localName) {
            throw new \RuntimeException(sprintf('%s ne contient pas de racine <svg>.', $fichier));
        }

        $viewBox = $racine->getAttribute('viewBox');
        if ('' === $viewBox) {
            throw new \RuntimeException(sprintf('%s n\'a pas de viewBox : le symbole ne saurait pas se dimensionner.', $fichier));
        }

        $contenu = '';
        foreach ($racine->childNodes as $enfant) {
            $contenu .= $document->saveXML($enfant);
        }

        // Un seul document pour tous les symboles : les ids internes des
        // exports (masques, clips, filtres) doivent devenir uniques.
        $contenu = (string) preg_replace_callback(
            '/\b(id="|url\(#|href="#)([\w-]+)/',
            static fn (array $m): string => $m[1].$id.'_'.$m[2],
            $contenu,
        );

        return sprintf('<symbol id="%s" viewBox="%s">%s</symbol>', $id, $viewBox, trim($contenu));
    }
}
