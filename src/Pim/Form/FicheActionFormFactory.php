<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Shared\Form\ActionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

final readonly class FicheActionFormFactory
{
    public function __construct(
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
    ) {}

    /**
     * @param array<string, string> $buttonAttr Attributs du bouton (data-variant/data-size/data-full pour le thème).
     *
     * @return FormInterface<mixed>
     */
    public function action(string $domain, string $id, string $name, string $label, bool $confirmDelete = false, string $confirmMessage = 'Supprimer cette fiche ?', array $buttonAttr = []): FormInterface
    {
        $options = [
            'action' => $this->urls->generate('app_pim_'.$domain.'_'.$name, ['id' => $id]),
            'button_label' => $label,
            'button_attr' => $buttonAttr,
            'csrf_token_id' => $name.'-'.$domain.'-'.$id,
        ];
        if ($confirmDelete) {
            $options['attr'] = [
                'data-controller' => 'confirm',
                'data-confirm-message-value' => $confirmMessage,
                'data-action' => 'submit->confirm#submit',
            ];
        }

        return $this->forms->createNamed($name.'_'.$domain, ActionType::class, null, $options);
    }

    /**
     * Bouton « Envoyer à Salesforce » de la fiche : action indépendante de la
     * gamme (une seule route pour tous les types).
     *
     * @param array<string, string> $buttonAttr
     *
     * @return FormInterface<mixed>
     */
    public function salesforce(string $id, array $buttonAttr = []): FormInterface
    {
        return $this->forms->createNamed('salesforce_fiche', ActionType::class, null, [
            'action' => $this->urls->generate('app_pim_fiche_salesforce', ['id' => $id]),
            'button_label' => 'Envoyer à Salesforce',
            'button_attr' => $buttonAttr,
            'csrf_token_id' => 'salesforce-fiche-'.$id,
        ]);
    }

    /**
     * Bouton « Enrichir ce qui manque » de la fiche : enfile le scan de toutes
     * les sources d'enrichissement, quelle que soit la gamme.
     *
     * @param array<string, string> $buttonAttr
     *
     * @return FormInterface<mixed>
     */
    public function enrichir(string $id, array $buttonAttr = []): FormInterface
    {
        return $this->forms->createNamed('enrichir_fiche', ActionType::class, null, [
            'action' => $this->urls->generate('app_pim_fiche_enrichir', ['id' => $id]),
            'button_label' => 'Enrichir ce qui manque',
            'button_attr' => $buttonAttr,
            'csrf_token_id' => 'enrichir-fiche-'.$id,
        ]);
    }

    /**
     * Bouton « Valider et publier » d'une fiche en attente : les deux
     * transitions en un clic, indépendant de la gamme.
     *
     * @param array<string, string> $buttonAttr
     *
     * @return FormInterface<mixed>
     */
    public function validerPublier(string $id, array $buttonAttr = []): FormInterface
    {
        return $this->forms->createNamed('valider_publier_fiche', ActionType::class, null, [
            'action' => $this->urls->generate('app_pim_fiche_valider_publier', ['id' => $id]),
            'button_label' => 'Valider et publier',
            'button_attr' => $buttonAttr,
            'csrf_token_id' => 'valider-publier-fiche-'.$id,
        ]);
    }

    /** @return FormInterface<mixed> */
    public function reject(string $domain, string $id): FormInterface
    {
        return $this->forms
            ->createNamedBuilder('reject_'.$domain)
            ->setAction($this->urls->generate('app_pim_'.$domain.'_reject', ['id' => $id]))
            ->setMethod('POST')
            ->add('reason', TextareaType::class, [
                'label' => 'Motif du refus',
                'required' => true,
                'constraints' => [new NotBlank(message: 'Le motif du refus est obligatoire.')],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Refuser'])
            ->getForm();
    }
}
