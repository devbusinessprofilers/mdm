<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Enum\TypeFiche;
use App\Pim\Service\ChampSuggestionService;
use App\Pim\Service\DescriptionIaVerifier;
use App\Pim\Service\EnrichissementIndisponibleException;
use App\Tests\Support\ParametresFixes;
use App\Vision\Service\OpenAiProviderException;
use App\Vision\Service\TextSuggestionProviderInterface;
use PHPUnit\Framework\TestCase;

final class DescriptionIaVerifierTest extends TestCase
{
    public function testUneDescriptionVideProduitUneSuggestionAvecLeTexteCompletEnPayload(): void
    {
        $texte = str_repeat('Un château au calme, à deux pas de la gare. ', 10);
        $propositions = $this->verifier($texte)->analyser($this->fiche(), null, 'lieu_desc_generale');

        self::assertCount(1, $propositions);
        $proposition = $propositions[0];
        self::assertSame(SuggestionAction::RemplirChamp, $proposition->action);
        self::assertSame('lieu_desc_generale', $proposition->champ);
        self::assertNull($proposition->valeurActuelle);
        // Colonne « Proposé » bornée : aperçu tronqué, texte complet en payload.
        self::assertSame(mb_substr(trim($texte), 0, 200).'…', $proposition->valeurProposee);
        self::assertSame(trim($texte), $proposition->payload['text'] ?? null);
    }

    public function testUneDescriptionCourteEstProposeeSansTroncature(): void
    {
        $propositions = $this->verifier('Charmant hôtel de centre-ville.')->analyser($this->fiche(), '', 'activite_desc_generale');

        self::assertCount(1, $propositions);
        self::assertSame('Charmant hôtel de centre-ville.', $propositions[0]->valeurProposee);
    }

    public function testUneDescriptionDejaRemplieNeProduitRien(): void
    {
        self::assertSame([], $this->verifier('Ne doit pas être appelé.')->analyser($this->fiche(), 'Déjà décrite.', 'lieu_desc_generale'));
    }

    public function testLeGateOpenAiCoupeLaSource(): void
    {
        self::assertSame([], $this->verifier('Réponse.', actif: false)->analyser($this->fiche(), null, 'lieu_desc_generale'));
    }

    public function testUneErreurRetryableSignaleLaSourceIndisponible(): void
    {
        $verifier = $this->verifier(null, erreur: new OpenAiProviderException('Trop de requêtes.', true));

        $this->expectException(EnrichissementIndisponibleException::class);
        $verifier->analyser($this->fiche(), null, 'lieu_desc_generale');
    }

    public function testUneErreurDefinitiveEstAbsorbee(): void
    {
        $verifier = $this->verifier(null, erreur: new OpenAiProviderException('Clé invalide.', false));

        self::assertSame([], $verifier->analyser($this->fiche(), null, 'lieu_desc_generale'));
    }

    private function verifier(?string $reponse, bool $actif = true, ?OpenAiProviderException $erreur = null): DescriptionIaVerifier
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
            'openai.suggestion_prompt' => '{contexte} / {champ} / {valeur}',
            'openai.suggestion_modele' => 'modele-test',
        ]);

        return new DescriptionIaVerifier(new ChampSuggestionService($provider, $parametres), $parametres);
    }

    private function fiche(): Fiche
    {
        $fiche = new Fiche(TypeFiche::Lieu);
        $fiche->changeLabel('Château des suggestions');

        return $fiche;
    }
}
