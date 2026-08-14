<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Shared\Form\ActionType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Formulaires Accepter / Ignorer d'une suggestion d'adresse BAN — le même
 * nom et le même jeton CSRF partout où la décision se prend (bloc
 * « Suggestions en attente » de la fiche, écran Qualité) et côté contrôleur
 * pour la validation de la soumission.
 */
final readonly class AdresseSuggestionFormFactory
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
    public function action(string $ficheId, string $decision, ?string $retour = null): FormInterface
    {
        return $this->forms->createNamed('adresse_'.$decision.'_'.$ficheId, ActionType::class, null, [
            'action' => $this->urls->generate('app_mdm_fiche_adresse_suggestion', array_filter([
                'id' => $ficheId,
                'decision' => $decision,
                'retour' => $retour,
            ])),
            'button_label' => 'accepter' === $decision ? 'Accepter' : 'Ignorer',
            'csrf_token_id' => 'adresse-'.$decision.'-'.$ficheId,
        ]);
    }
}
