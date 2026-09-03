<?php

declare(strict_types=1);

namespace App\Tests\Shared\Text;

use App\Shared\Text\TexteBrut;
use PHPUnit\Framework\TestCase;

final class TexteBrutTest extends TestCase
{
    public function testLeHtmlDeTinymceAvecEntitesNommeesRedevientDuTexte(): void
    {
        $html = '<p>Perch&eacute;e sur les rives de l&rsquo;Oise, chef-d\'&oelig;uvre du XIXe si&egrave;cle.</p><p>&zwj;&Agrave; deux pas, Jeanne &amp; the Forest.</p>';

        self::assertSame(
            "Perchée sur les rives de l’Oise, chef-d'œuvre du XIXe siècle.\n\nÀ deux pas, Jeanne & the Forest.",
            TexteBrut::depuisHtml($html),
        );
    }

    public function testLesParagraphesRetoursEtPucesSontConserves(): void
    {
        $html = "<p>Ligne 1<br />Ligne 2</p>\n<ul>\n<li>Salle A</li>\n<li>Salle B</li>\n</ul>\n<p>Fin&nbsp;!</p>";

        self::assertSame("Ligne 1\nLigne 2\n\n- Salle A\n- Salle B\n\nFin !", TexteBrut::depuisHtml($html));
    }

    public function testLesCollagesSauvagesSontNettoyes(): void
    {
        $html = '<!-- x-tinymce/html --><span style="color: rgb(34, 22, 68); font-family: Outfit, sans-serif;">Offrez à vos équipes</span>'
            ."\n".'<table class="table"><tr><td>une expérience</td></tr></table><script>alert(1)</script><span style="display: none;"> </span>unique.';

        self::assertSame("Offrez à vos équipes\n\nune expérience\n\nunique.", TexteBrut::depuisHtml($html));
        // Collage tronqué par la taille du champ : la balise finale n'est jamais fermée.
        self::assertSame('Le maître mot.', TexteBrut::depuisHtml('<p>Le ma&icirc;tre mot. <span style="color: rgb(1, 2, 3); font-fami'));
    }

    public function testUnTexteBrutEstRenduTelQuelMemeAvecUnSigneInferieur(): void
    {
        $texte = "Capacité < 100 personnes, budget > 5 000 €.\nDeuxième ligne.";

        self::assertSame($texte, TexteBrut::depuisHtml($texte));
        self::assertSame("Espaces et\nWindows", TexteBrut::depuisHtml("  Espaces  et \r\nWindows \n\n\n"));
    }

    public function testLeTexteBrutDevientDesParagraphesPourLEditeur(): void
    {
        $texte = "Ligne 1 & <ok>\nLigne 2\n\n- Salle A\n- Salle B\n\nFin !";

        self::assertSame(
            '<p>Ligne 1 &amp; &lt;ok&gt;<br>Ligne 2</p><ul><li>Salle A</li><li>Salle B</li></ul><p>Fin !</p>',
            TexteBrut::versHtml($texte),
        );
        self::assertSame('', TexteBrut::versHtml("  \n "));
    }

    public function testLAllerRetourEditeurEstStable(): void
    {
        $texte = "Perchée sur l’Oise.\nJeanne & the Forest.\n\n- Salle A\n- Salle B\n\nFin.";

        self::assertSame($texte, TexteBrut::depuisHtml(TexteBrut::versHtml($texte)));
    }
}
