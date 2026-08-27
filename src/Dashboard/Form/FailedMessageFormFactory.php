<?php

declare(strict_types=1);

namespace App\Dashboard\Form;

use App\Shared\Form\ActionType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Formulaires Réessayer / Supprimer d'un message de la DLQ (page
 * /admin/performance) : même nom et même jeton CSRF au rendu et à la
 * validation côté contrôleur.
 */
final readonly class FailedMessageFormFactory
{
    public function __construct(
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * Décore les lignes de la DLQ avec leurs vues de formulaires, prêtes pour
     * le fragment _performance_tableaux (les lignes sans id sont écartées).
     *
     * @param list<array<string, mixed>> $lignes
     *
     * @return list<array<string, mixed>>
     */
    public function lignesAvecFormulaires(array $lignes): array
    {
        $decorees = [];
        foreach ($lignes as $ligne) {
            if (null === $ligne['id']) {
                continue;
            }
            $id = (string) $ligne['id'];
            $decorees[] = $ligne + [
                'form_reessayer' => $this->action($id, 'reessayer')->createView(),
                'form_supprimer' => $this->action($id, 'supprimer')->createView(),
            ];
        }

        return $decorees;
    }

    /**
     * @param 'reessayer'|'supprimer' $action
     *
     * @return FormInterface<mixed>
     */
    public function action(string $id, string $action): FormInterface
    {
        return $this->forms->createNamed('failed_'.$action.'_'.$id, ActionType::class, null, [
            'action' => $this->urls->generate('app_performance_failed_'.$action, ['id' => $id]),
            'button_label' => 'reessayer' === $action ? 'Réessayer' : 'Supprimer',
            'csrf_token_id' => 'failed-'.$action.'-'.$id,
            'attr' => 'supprimer' === $action ? [
                'data-controller' => 'confirm',
                'data-confirm-message-value' => 'Supprimer définitivement ce message en échec ?',
                'data-action' => 'submit->confirm#submit',
            ] : [],
        ]);
    }
}
