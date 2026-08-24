<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Shared\Form\ActionType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Boutons Accepter / Ignorer d'une suggestion d'enrichissement générique
 * (FicheSuggestion), même nom et même jeton CSRF partout où la décision se
 * prend (bloc « Suggestions en attente », écran Qualité) et côté contrôleur.
 */
final readonly class EnrichissementSuggestionFormFactory
{
    public function __construct(
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @param string  $decision 'accepter' ou 'ignorer'
     * @param ?string $retour   'qualite' pour revenir à l'écran Qualité après la décision
     *
     * @return FormInterface<mixed>
     */
    public function action(string $suggestionId, string $decision, ?string $retour = null): FormInterface
    {
        return $this->forms->createNamed('enrichissement_'.$decision.'_'.$suggestionId, ActionType::class, null, [
            'action' => $this->urls->generate('app_mdm_enrichissement_suggestion', array_filter([
                'id' => $suggestionId,
                'decision' => $decision,
                'retour' => $retour,
            ])),
            'button_label' => 'accepter' === $decision ? 'Accepter' : 'Ignorer',
            'csrf_token_id' => 'enrichissement-'.$decision.'-'.$suggestionId,
        ]);
    }
}
