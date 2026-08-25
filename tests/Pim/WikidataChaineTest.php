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

        // L'enseigne présente dans le nom est retournée avec son groupe.
        $canopy = $dico->detecter('Canopy by Hilton Cannes');
        self::assertNotNull($canopy);
        self::assertSame('Canopy by Hilton', $canopy->enseigne);
        self::assertSame('Hilton', $canopy->groupe);
        $ibis = $dico->detecter('Ibis Budget Lyon Part-Dieu');
        self::assertNotNull($ibis);
        self::assertSame('Ibis Budget', $ibis->enseigne);
        self::assertSame('Accor', $ibis->groupe);
        $bw = $dico->detecter('Best Western Plus Le Faubourg');
        self::assertNotNull($bw);
        self::assertSame('Best Western', $bw->enseigne);
        self::assertSame('Best Western', $bw->groupe);
        self::assertNull($dico->detecter('Hôtel de la Gare'));
    }

    public function testDictionnaireEnrichiParWikidata(): void
    {
        $dico = ChaineDictionnaire::depuis([new WikidataChaine('Citadines', ['citadines apart hotel'])]);

        $detection = $dico->detecter('Citadines Croisette Cannes');
        self::assertNotNull($detection);
        self::assertSame('Citadines', $detection->enseigne);
        self::assertSame('Citadines', $detection->groupe);
    }

    public function testVerifierProposeLEnseigneDetecteeAvecSonGroupe(): void
    {
        $lieu = new Lieu();
        $lieu->changeLabel('Mercure Paris Centre Tour Eiffel');

        $propositions = (new ChaineHoteliereVerifier())->analyser($lieu, ChaineDictionnaire::depuis());

        self::assertCount(1, $propositions);
        self::assertSame('lieu_chaine', $propositions[0]->champ);
        // La MARQUE (dans la LOV) est proposée, le groupe reste en information.
        self::assertSame('Mercure', $propositions[0]->valeurProposee);
        self::assertSame(['groupe' => 'Accor'], $propositions[0]->payload);
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
