<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Service\ChaineHoteliereVerifier;
use App\Pim\Service\Wikidata\ChaineDictionnaire;
use App\Pim\Service\Wikidata\WikidataChaine;
use App\Pim\Service\Wikidata\WikidataChaineClient;
use PHPUnit\Framework\TestCase;

final class WikidataChaineTest extends TestCase
{
    public function testMapperRegroupeLabelsEtAlias(): void
    {
        $chaines = WikidataChaineClient::mapper(['results' => ['bindings' => [
            ['item' => ['value' => 'wd:Q209201'], 'itemLabel' => ['value' => 'AccorHotels'], 'alt' => ['value' => 'Accor']],
            ['item' => ['value' => 'wd:Q209201'], 'itemLabel' => ['value' => 'AccorHotels'], 'alt' => ['value' => 'Groupe Accor']],
            ['item' => ['value' => 'wd:Q598'], 'itemLabel' => ['value' => 'Hilton']],
            ['item' => ['value' => 'wd:Q999'], 'itemLabel' => ['value' => 'Q999']],
        ]]]);

        self::assertCount(2, $chaines);
        self::assertSame('AccorHotels', $chaines[0]->nom);
        self::assertSame(['Accor', 'Groupe Accor'], $chaines[0]->alias);
        self::assertSame('Hilton', $chaines[1]->nom);
    }

    public function testDictionnaireDetecteViaReferentielInterne(): void
    {
        $dico = ChaineDictionnaire::depuis();

        self::assertSame('Hilton', $dico->detecter('Canopy by Hilton Cannes'));
        self::assertSame('Accor', $dico->detecter('Ibis Budget Lyon Part-Dieu'));
        self::assertSame('Best Western', $dico->detecter('Best Western Plus Le Faubourg'));
        self::assertNull($dico->detecter('Hôtel de la Gare'));
    }

    public function testDictionnaireEnrichiParWikidata(): void
    {
        $dico = ChaineDictionnaire::depuis([new WikidataChaine('Citadines', ['citadines apart hotel'])]);

        self::assertSame('Citadines', $dico->detecter('Citadines Croisette Cannes'));
    }

    public function testVerifierProposeLaChaineDetectee(): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Mercure Paris Centre Tour Eiffel');

        $propositions = (new ChaineHoteliereVerifier())->analyser($lieu, ChaineDictionnaire::depuis());

        self::assertCount(1, $propositions);
        self::assertSame('lieu_chaine', $propositions[0]->champ);
        self::assertSame('Accor', $propositions[0]->valeurProposee);
    }

    public function testVerifierNeProposeRienSiChaineDejaRenseignee(): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Mercure Paris Centre');
        // Le sélecteur LOV est l'unique champ chaîne : déjà coché = pas de suggestion.
        $lieu->changeGeneraleChainesGroupeHot(['GENERALE_CHAINES_GROUPE_HOT_40']);

        self::assertSame([], (new ChaineHoteliereVerifier())->analyser($lieu, ChaineDictionnaire::depuis()));
    }
}
