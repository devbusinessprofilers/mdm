<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Enum\TriReferentiel;
use App\Pim\Form\ReferentielFiltres;
use PHPUnit\Framework\TestCase;

final class TriReferentielTest extends TestCase
{
    public function testLeDefautEstLaModificationDecroissante(): void
    {
        self::assertSame(TriReferentiel::ModifDesc, TriReferentiel::DEFAUT);
        self::assertSame('modif', TriReferentiel::ModifDesc->colonne());
        self::assertSame('DESC', TriReferentiel::ModifDesc->direction());
        self::assertSame('nom', TriReferentiel::NomAsc->colonne());
        self::assertSame('ASC', TriReferentiel::NomAsc->direction());
    }

    public function testLaPertinenceEstUnTriDescendantHorsColonnes(): void
    {
        self::assertSame('pertinence', TriReferentiel::Pertinence->colonne());
        self::assertSame('DESC', TriReferentiel::Pertinence->direction());
        // Depuis la pertinence, chaque en-tête part sur son sens naturel.
        self::assertSame(TriReferentiel::NomAsc, TriReferentiel::pourColonne('nom', TriReferentiel::Pertinence));
        self::assertSame(TriReferentiel::ModifDesc, TriReferentiel::pourColonne('modif', TriReferentiel::Pertinence));
    }

    public function testLaRechercheActiveBasculeLeDefautSurLaPertinence(): void
    {
        // Sans tri explicite, une recherche implique la pertinence…
        self::assertSame(TriReferentiel::Pertinence, ReferentielFiltres::fromArray(['q' => 'hotel'])->tri);
        self::assertSame(TriReferentiel::DEFAUT, ReferentielFiltres::fromArray([])->tri);
        // … et la pertinence forgée sans recherche retombe sur le défaut.
        self::assertSame(TriReferentiel::DEFAUT, ReferentielFiltres::fromArray(['tri' => 'pertinence'])->tri);
        self::assertSame(TriReferentiel::Pertinence, ReferentielFiltres::fromArray(['q' => 'hotel', 'tri' => 'pertinence'])->tri);

        // Le défaut contextuel est omis des URL et vues enregistrées ; un tri
        // explicite sous recherche est émis, même modif_desc.
        $filtres = ReferentielFiltres::fromArray(['q' => 'hotel']);
        self::assertArrayNotHasKey('tri', $filtres->toArray());
        $filtres->tri = TriReferentiel::ModifDesc;
        self::assertSame('modif_desc', $filtres->toArray()['tri']);
    }

    public function testUnClicSurUneColonneInactivePrendSonSensNaturel(): void
    {
        // Alphabétique ascendant pour les libellés…
        self::assertSame(TriReferentiel::NomAsc, TriReferentiel::pourColonne('nom', TriReferentiel::ModifDesc));
        self::assertSame(TriReferentiel::PaysAsc, TriReferentiel::pourColonne('pays', TriReferentiel::NomAsc));
        // … descendant pour les dates et quantités.
        self::assertSame(TriReferentiel::CompletudeDesc, TriReferentiel::pourColonne('completude', TriReferentiel::NomAsc));
        self::assertSame(TriReferentiel::DiffusionDesc, TriReferentiel::pourColonne('diffusion', TriReferentiel::NomAsc));
        self::assertSame(TriReferentiel::ModifDesc, TriReferentiel::pourColonne('modif', TriReferentiel::NomAsc));
    }

    public function testUnClicSurLaColonneActiveInverseLeSens(): void
    {
        self::assertSame(TriReferentiel::NomDesc, TriReferentiel::pourColonne('nom', TriReferentiel::NomAsc));
        self::assertSame(TriReferentiel::NomAsc, TriReferentiel::pourColonne('nom', TriReferentiel::NomDesc));
        self::assertSame(TriReferentiel::ModifAsc, TriReferentiel::pourColonne('modif', TriReferentiel::ModifDesc));
    }

    public function testLeTriTransiteParLesFiltresSansCompterCommeFiltreActif(): void
    {
        $filtres = new ReferentielFiltres();
        // Au défaut, la clé est omise : URL et vues enregistrées inchangées.
        self::assertArrayNotHasKey('tri', $filtres->toArray());

        $filtres->tri = TriReferentiel::NomAsc;
        self::assertSame('nom_asc', $filtres->toArray()['tri']);
        self::assertSame(0, $filtres->actifs());

        $relus = ReferentielFiltres::fromArray($filtres->toArray());
        self::assertSame(TriReferentiel::NomAsc, $relus->tri);

        // Valeur inconnue ou absente : repli silencieux sur le défaut.
        self::assertSame(TriReferentiel::DEFAUT, ReferentielFiltres::fromArray(['tri' => 'nimporte_quoi'])->tri);
        self::assertSame(TriReferentiel::DEFAUT, ReferentielFiltres::fromArray([])->tri);
    }
}
