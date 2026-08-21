<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Shared\Form\ActionType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Formulaires « Confirmer le doublon » / « Ignorer » d'une alerte de doublon de
 * texte, avec le même nom et le même jeton CSRF côté rendu (écran Qualité) et
 * côté contrôleur (validation de la soumission).
 */
final readonly class TextDuplicateFormFactory
{
    public function __construct(
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @param string $decision 'confirmer' ou 'ignorer'
     *
     * @return FormInterface<mixed>
     */
    public function action(string $alertId, string $decision): FormInterface
    {
        return $this->forms->createNamed('doublon_texte_'.$decision.'_'.$alertId, ActionType::class, null, [
            'action' => $this->urls->generate('app_mdm_qualite_doublon_texte', [
                'id' => $alertId,
                'decision' => $decision,
            ]),
            'button_label' => 'confirmer' === $decision ? 'Confirmer le doublon' : 'Ignorer',
            'button_attr' => ['data-variant' => 'confirmer' === $decision ? 'primary' : 'outline', 'data-size' => 'sm', 'data-full' => '0'],
            'csrf_token_id' => 'doublon-texte-'.$decision.'-'.$alertId,
        ]);
    }
}
