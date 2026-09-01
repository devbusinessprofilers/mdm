<?php

declare(strict_types=1);

namespace App\Vision\Form;

use App\Shared\Form\ActionType;
use App\Vision\Entity\ImageRecognition;
use App\Vision\Enum\EnhancementStatus;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class VisionFormFactory
{
    public function __construct(
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** @return FormInterface<mixed> */
    public function lancementRetouche(): FormInterface
    {
        return $this->forms->createNamed('retouche_lancement', VisionLancementType::class, null, [
            'action' => $this->urls->generate('app_mdm_retouche_lancer'),
            'csrf_token_id' => 'vision-retouche-lancer',
            'button_label' => 'Lancer la retouche IA',
        ]);
    }

    /** @return FormInterface<mixed> */
    public function lancementRetoucheAuto(): FormInterface
    {
        return $this->forms->createNamed('retouche_auto_lancement', VisionLancementType::class, null, [
            'action' => $this->urls->generate('app_mdm_retouche_lancer_auto'),
            'csrf_token_id' => 'vision-retouche-auto-lancer',
            'button_label' => 'Lancer la retouche automatique',
        ]);
    }

    /**
     * Bouton du lancement en masse sur les photos sans mots-clés (onglet Reconnaissance IA).
     *
     * @return FormInterface<mixed>
     */
    public function lancementRecoMasse(): FormInterface
    {
        return $this->forms->createNamed('reco_masse', ActionType::class, null, [
            'action' => $this->urls->generate('app_mdm_reco_lancer_masse'),
            'csrf_token_id' => 'vision-reco-lancer-masse',
            'button_label' => 'Lancer sur les photos sans mots-clés',
        ]);
    }

    /** @return FormInterface<mixed> */
    public function lancementReco(): FormInterface
    {
        return $this->forms->createNamed('reco_lancement', VisionLancementType::class, null, [
            'action' => $this->urls->generate('app_mdm_reco_lancer'),
            'csrf_token_id' => 'vision-reco-lancer',
            'button_label' => 'Lancer la reconnaissance IA',
        ]);
    }

    /**
     * Formulaires d'action par ligne de l'onglet retouche, selon le statut.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    public function addRetoucheActions(array $items, int $page, string $pageParam = 'page'): array
    {
        foreach ($items as &$item) {
            $enhancement = $item['enhancement'];
            if (!$enhancement instanceof \App\Vision\Entity\ImageEnhancement) {
                throw new \LogicException('Retouche attendue pour les formulaires d’action.');
            }
            $query = ['onglet' => 'import', $pageParam => $page];
            if (EnhancementStatus::Ready === $enhancement->status()) {
                $item['accept_form'] = $this->action('app_mdm_retouche_accepter', ['id' => $enhancement->id()] + $query, 'vision-retouche-accepter-'.$enhancement->id(), 'Accepter', ['class' => 'btn btn-secondary']);
                $item['reject_form'] = $this->action(
                    'app_mdm_retouche_rejeter',
                    ['id' => $enhancement->id()] + $query,
                    'vision-retouche-rejeter-'.$enhancement->id(),
                    'Rejeter',
                    ['class' => 'btn danger'],
                    [
                        'data-controller' => 'confirm',
                        'data-confirm-message-value' => 'Rejeter cette retouche ? La version proposée sera supprimée.',
                        'data-action' => 'submit->confirm#submit',
                    ],
                );
            } elseif (EnhancementStatus::Failed === $enhancement->status()) {
                $item['retry_form'] = $this->action('app_mdm_retouche_relancer', ['id' => $enhancement->id()] + $query, 'vision-retouche-relancer-'.$enhancement->id(), 'Relancer', ['class' => 'btn']);
            } elseif (EnhancementStatus::Accepted === $enhancement->status() && $enhancement->media()->isEnhanced()) {
                $item['revert_form'] = $this->action(
                    'app_mdm_retouche_revenir',
                    ['id' => $enhancement->id()] + $query,
                    'vision-retouche-revenir-'.$enhancement->id(),
                    'Revenir à l’original',
                    ['class' => 'btn btn-secondary'],
                    [
                        'data-controller' => 'confirm',
                        'data-confirm-message-value' => 'Revenir à l’original ? Les variantes seront régénérées depuis la photo déposée.',
                        'data-action' => 'submit->confirm#submit',
                    ],
                );
            }
        }
        unset($item);

        return $items;
    }

    /**
     * Formulaires par ligne de l'onglet reco : revue des suggestions en
     * attente et relance des analyses en échec.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    public function addRecoActions(array $items, int $page): array
    {
        foreach ($items as &$item) {
            $recognition = $item['recognition'];
            if (!$recognition instanceof ImageRecognition) {
                throw new \LogicException('Reconnaissance attendue pour les formulaires d’action.');
            }
            $query = ['onglet' => 'ia', 'page' => $page];
            if (in_array($recognition->status()->value, ['ready', 'partially_reviewed'], true)) {
                $item['review_form'] = $this->review($recognition, $this->urls->generate('app_mdm_reco_valider', ['id' => $recognition->id()] + $query))->createView();
            } elseif ('failed' === $recognition->status()->value) {
                $item['retry_form'] = $this->action('app_mdm_reco_relancer', ['id' => $recognition->id()] + $query, 'vision-reco-relancer-'.$recognition->id(), 'Relancer', ['class' => 'btn']);
            }
        }
        unset($item);

        return $items;
    }

    /** @return FormInterface<array<string, mixed>> */
    public function review(ImageRecognition $recognition, string $action): FormInterface
    {
        $builder = $this->forms->createNamedBuilder('reco_review_'.$recognition->id(), FormType::class, null, [
            'action' => $action,
            'method' => 'POST',
            'csrf_token_id' => 'vision-reco-review-'.$recognition->id(),
            'attr' => ['data-controller' => 'ocr-review'],
        ]);
        foreach ($recognition->suggestions() as $suggestion) {
            if (!$suggestion->isPending()) {
                continue;
            }
            $value = $suggestion->correctedValue();
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $row = $builder->create($suggestion->id(), FormType::class, ['data_class' => null, 'label' => false])
                ->add('value', TextareaType::class, ['label' => false, 'data' => $value, 'attr' => ['rows' => 2]])
                ->add('accept', CheckboxType::class, ['label' => 'Valider', 'required' => false, 'attr' => ['data-action' => 'ocr-review#toggle', 'data-ocr-choice' => 'accept', 'data-ocr-opposite' => 'reject']])
                ->add('reject', CheckboxType::class, ['label' => 'Refuser', 'required' => false, 'attr' => ['data-action' => 'ocr-review#toggle', 'data-ocr-choice' => 'reject', 'data-ocr-opposite' => 'accept']]);
            $builder->add($row);
        }
        $builder->add('save', SubmitType::class, ['label' => 'Appliquer mes décisions']);

        return $builder->getForm();
    }

    /**
     * Décisions par suggestion depuis la soumission de la revue : seules les
     * lignes explicitement tranchées (validées ou refusées) sont retenues,
     * les autres restent en attente.
     *
     * @param array<string, mixed> $raw
     *
     * @return array<string, array{value: ?string, accept: bool}>
     */
    public static function decisionsDepuisSoumission(array $raw): array
    {
        $decisions = [];
        foreach ($raw as $suggestionId => $input) {
            if ('save' === $suggestionId || !is_array($input)) {
                continue;
            }
            $accept = true === ($input['accept'] ?? false);
            $reject = true === ($input['reject'] ?? false);
            if ($accept === $reject) {
                continue;
            }
            $value = $input['value'] ?? null;
            $decisions[(string) $suggestionId] = ['value' => is_string($value) ? $value : null, 'accept' => $accept];
        }

        return $decisions;
    }

    /**
     * @param array<string, string|int> $parameters
     * @param array<string, mixed>      $buttonAttributes
     * @param array<string, mixed>      $formAttributes
     */
    private function action(string $route, array $parameters, string $csrfTokenId, string $label, array $buttonAttributes, array $formAttributes = []): FormView
    {
        return $this->forms->createNamed('', ActionType::class, null, [
            'action' => $this->urls->generate($route, $parameters),
            'button_label' => $label,
            'button_attr' => $buttonAttributes,
            'attr' => $formAttributes,
            'csrf_token_id' => $csrfTokenId,
        ])->createView();
    }
}
