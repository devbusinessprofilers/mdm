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

    /** @return FormInterface<mixed> */
    public function action(string $domain, string $id, string $name, string $label, bool $confirmDelete = false, string $confirmMessage = 'Supprimer cette fiche ?'): FormInterface
    {
        $options = [
            'action' => $this->urls->generate('app_pim_'.$domain.'_'.$name, ['id' => $id]),
            'button_label' => $label,
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
