<?php

declare(strict_types=1);

namespace App\Tests\Pim\Import;

use App\Pim\Import\SuggestionProche;
use PHPUnit\Framework\TestCase;

final class SuggestionProcheTest extends TestCase
{
    public function testTrouveLaCorrectionDUneTypoSimple(): void
    {
        self::assertSame('adresse_ville', SuggestionProche::trouver('adresse_vile', ['code', 'adresse_ville', 'adresse_cp']));
    }

    public function testNormaliseAccentsEtCasseAvantLaDistance(): void
    {
        self::assertSame('Séminaire PARIS', SuggestionProche::trouver('seminaire paris', ['Lyon', 'Séminaire PARIS']));
    }

    public function testDepartageLesExAequoParLongueurPuisAlphabet(): void
    {
        // « aa » est à distance 1 de « aaa » comme de « ab » : le plus court
        // gagne, quel que soit l'ordre de la liste (les LOV sont triées par
        // libellé en base).
        self::assertSame('ab', SuggestionProche::trouver('aa', ['aaa', 'ab']));
        self::assertSame('ab', SuggestionProche::trouver('aa', ['ab', 'aaa']));
        self::assertSame('ab', SuggestionProche::trouver('aa', ['ac', 'ab']));
    }

    public function testPasDeSuggestionSiTropEloigne(): void
    {
        self::assertNull(SuggestionProche::trouver('zzzz', ['adresse_ville', 'code']));
    }

    public function testPasDeSuggestionSansCandidatOuSaisieVide(): void
    {
        self::assertNull(SuggestionProche::trouver('adresse_vile', []));
        self::assertNull(SuggestionProche::trouver('   ', ['adresse_ville']));
    }
}
