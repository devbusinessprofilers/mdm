<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Service\AtoutsIaVerifier;
use App\Pim\Service\ChampSuggestionService;
use App\Pim\Service\EnrichissementIndisponibleException;
use App\Tests\Support\ParametresFixes;
use App\Vision\Service\OpenAiProviderException;
use App\Vision\Service\TextSuggestionProviderInterface;
use PHPUnit\Framework\TestCase;

final class AtoutsIaVerifierTest extends TestCase
{
    public function testLaReponseEstRevalideeLigneALigne(): void
    {
        // Puces et numérotations retirées, doublon écarté, atout trop long
        // écarté (jamais tronqué), liste plafonnée à max.
        $reponse = "- Parc de 5 hectares\n2. Salles modulables\n• Parc de 5 hectares\nUn atout beaucoup trop long pour tenir dans la limite imposée\nRooftop panoramique\nParking privé\nProche gare TGV\nSpa sur place";
        $propositions = $this->verifier($reponse)->analyser($this->fiche(), 'Une belle description.', 'lieu_atouts', [], 5, 35);

        self::assertCount(1, $propositions);
        self::assertSame('lieu_atouts', $propositions[0]->champ);
        self::assertSame(
            ['Parc de 5 hectares', 'Salles modulables', 'Rooftop panoramique', 'Parking privé', 'Proche gare TGV'],
            $propositions[0]->payload['liste'] ?? null,
        );
        self::assertNull($propositions[0]->valeurActuelle);
    }

    public function testSansDescriptionRienNestPropose(): void
    {
        $verifier = $this->verifier('Ne doit pas être appelé.');

        self::assertSame([], $verifier->analyser($this->fiche(), null, 'lieu_atouts', [], 5, 35));
        self::assertSame([], $verifier->analyser($this->fiche(), '<p>  </p>', 'lieu_atouts', [], 5, 35));
    }

    public function testDesAtoutsDejaSaisisBloquentLaProposition(): void
    {
        self::assertSame([], $this->verifier('Ne doit pas être appelé.')->analyser($this->fiche(), 'Description.', 'restaurant_atouts', ['Déjà là'], 5, 80));
    }

    public function testLeGateOpenAiCoupeLaSource(): void
    {
        self::assertSame([], $this->verifier('Réponse.', actif: false)->analyser($this->fiche(), 'Description.', 'lieu_atouts', [], 5, 35));
    }

    public function testUneErreurRetryableSignaleLaSourceIndisponible(): void
    {
        $verifier = $this->verifier(null, erreur: new OpenAiProviderException('Trop de requêtes.', true));

        $this->expectException(EnrichissementIndisponibleException::class);
        $verifier->analyser($this->fiche(), 'Description.', 'lieu_atouts', [], 5, 35);
    }

    public function testUneReponseInexploitableEstAbsorbee(): void
    {
        self::assertSame([], $this->verifier("   \n  ")->analyser($this->fiche(), 'Description.', 'lieu_atouts', [], 5, 35));
    }

    private function verifier(?string $reponse, bool $actif = true, ?OpenAiProviderException $erreur = null): AtoutsIaVerifier
    {
        $provider = new class($reponse, $erreur) implements TextSuggestionProviderInterface {
            public function __construct(private readonly ?string $reponse, private readonly ?OpenAiProviderException $erreur)
            {
            }

            public function suggerer(string $prompt, string $model): string
            {
                if (null !== $this->erreur) {
                    throw $this->erreur;
                }

                return (string) $this->reponse;
            }
        };
        $parametres = new ParametresFixes([
            'openai.actif' => $actif ? '1' : '0',
            'openai.atouts_prompt' => '{contexte} / {description} / {max} / {longueur_max}',
            'openai.suggestion_modele' => 'modele-test',
        ]);

        return new AtoutsIaVerifier(new ChampSuggestionService($provider, $parametres), $parametres);
    }

    private function fiche(): Fiche
    {
        $fiche = new Fiche(TypeFiche::Lieu);
        $fiche->changeLabel('Château des atouts');

        return $fiche;
    }
}
