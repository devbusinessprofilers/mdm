<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\RechercheCorrecteur;
use App\Pim\Service\VocabulaireRechercheInterface;
use PHPUnit\Framework\TestCase;

final class RechercheCorrecteurTest extends TestCase
{
    public function testCorrigeUnMotAbsentVersLeMotConnuLePlusProche(): void
    {
        // « pomme » → « paume » : distance 2, admise pour un mot de 5 lettres.
        $correcteur = self::correcteur(['auberge', 'jeu', 'paume']);

        $corrections = $correcteur->corrections('Auberge du jeu de la pomme');
        self::assertNotSame([], $corrections);
        self::assertSame('Auberge du jeu de la paume', $corrections[0]);
    }

    public function testProposeAussiLesSubstitutionsDeMotsConnus(): void
    {
        // « pomme » existe (« Hôtel La Pomme ») : la requête stricte échouera
        // quand même sur « auberge jeu pomme » — la substitution vers son
        // voisin « paume » doit figurer dans les candidates à sonder.
        $correcteur = self::correcteur(['auberge', 'jeu', 'paume', 'pomme']);

        self::assertContains('auberge jeu paume', $correcteur->corrections('auberge jeu pomme'));
    }

    public function testLeContexteDepartageLesVoisinsNombreux(): void
    {
        // « pomme » a cinq voisins à distance 1 qui évincent « paume »
        // (distance 2) du plafond global — mais seul « paume » co-occurre avec
        // « auberge » et « jeu » dans un nom de fiche : il doit sortir premier.
        $correcteur = self::correcteur(
            ['auberge', 'jeu', 'paume', 'pomme', 'somme', 'homme', 'comme', 'gomme', 'tomme'],
            ['auberge' => 1, 'jeu' => 1, 'paume' => 1, 'chantilly' => 1],
        );

        self::assertSame('auberge jeu paume', $correcteur->corrections('auberge jeu pomme')[0]);
    }

    public function testRienAProposerQuandAucunVoisinNExiste(): void
    {
        $correcteur = self::correcteur(['auberge', 'jeu', 'chantilly']);

        self::assertSame([], $correcteur->corrections('Auberge de Chantilly'));
    }

    public function testIgnoreLesRecherchesParCodeEtLesSaisiesVides(): void
    {
        $correcteur = self::correcteur(['auberge']);

        self::assertSame([], $correcteur->corrections('12345'));
        self::assertSame([], $correcteur->corrections('   '));
    }

    public function testSeuilResserreAUneLettrePourLesMotsCourts(): void
    {
        // « jai » est à distance 2 de « jeu » : trop pour un mot de 3 lettres.
        $correcteur = self::correcteur(['jeu', 'chantilly']);

        self::assertSame([], $correcteur->corrections('chantilly jai'));
    }

    public function testMotIncorrigeableConserveTelQuel(): void
    {
        $correcteur = self::correcteur(['auberge', 'paume']);

        self::assertSame([], $correcteur->corrections('xyzabc'));
    }

    public function testDepartageDeterministeParFrequencePuisAlphabet(): void
    {
        // « chateaus » est à distance 1 de « chateau » comme de « chateaux » :
        // la fréquence tranche pour la première candidate.
        $correcteur = self::correcteur(['chateau' => 1, 'chateaux' => 9]);
        self::assertSame('chateaux', $correcteur->corrections('chateaus')[0]);

        // À fréquences égales, l'ordre alphabétique tranche.
        $correcteur = self::correcteur(['chateau' => 3, 'chateaux' => 3]);
        self::assertSame('chateau', $correcteur->corrections('chateaus')[0]);
    }

    public function testLesVariantesSuiventLaMeilleureCandidate(): void
    {
        // Le voisin non retenu en premier reste sondable en candidate suivante.
        $correcteur = self::correcteur(['chateau' => 1, 'chateaux' => 9]);

        self::assertSame(['chateaux', 'chateau'], $correcteur->corrections('chateaus'));
    }

    public function testNormaliseAccentsEtCasseAvantComparaison(): void
    {
        $correcteur = self::correcteur(['auberge', 'paume']);

        // « Pômme » → pomme → paume ; « Aubérge » → auberge, connu donc conservé tel que saisi.
        self::assertSame('paume Aubérge', $correcteur->corrections('Pômme Aubérge')[0]);
    }

    public function testDernierTokenPartielPrefixeDUnMotConnuEstConserve(): void
    {
        // « chate » commence « chateau » : frappe en cours, on n'y touche pas —
        // alors qu'en requête complète il serait corrigé vers « chat ».
        $correcteur = self::correcteur(['chat', 'chateau']);

        self::assertSame([], $correcteur->corrections('chate', dernierTokenPartiel: true));
        self::assertSame(['chat', 'chateau'], $correcteur->corrections('chate'));
    }

    public function testDernierTokenPartielNonPrefixeEstCorrige(): void
    {
        $correcteur = self::correcteur(['auberge', 'paume']);

        self::assertSame('auberge paume', $correcteur->corrections('auberge pomme', dernierTokenPartiel: true)[0]);
    }

    /**
     * @param array<int|string, int|string> $mots     mot => fréquence, ou liste de mots (fréquence 1)
     * @param array<string, int>            $contexte vocabulaire renvoyé pour tout contexte
     */
    private static function correcteur(array $mots, array $contexte = []): RechercheCorrecteur
    {
        $parLongueur = [];
        foreach ($mots as $cle => $valeur) {
            $mot = is_string($cle) ? $cle : (string) $valeur;
            $parLongueur[strlen($mot)][$mot] = is_string($cle) ? (int) $valeur : 1;
        }

        return new RechercheCorrecteur(new class($parLongueur, $contexte) implements VocabulaireRechercheInterface {
            /**
             * @param array<int, array<string, int>> $mots
             * @param array<string, int>             $contexte
             */
            public function __construct(private readonly array $mots, private readonly array $contexte)
            {
            }

            public function motsParLongueur(): array
            {
                return $this->mots;
            }

            public function motsAuContexte(array $tokens): array
            {
                return $this->contexte;
            }
        });
    }
}
