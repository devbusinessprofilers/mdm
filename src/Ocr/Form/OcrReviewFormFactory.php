<?php

declare(strict_types=1);

namespace App\Ocr\Form;

use App\Ocr\Entity\DocumentExtraction;
use App\Shared\Form\ActionType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final readonly class OcrReviewFormFactory
{
    public function __construct(private FormFactoryInterface $forms) {}

    /** @return FormInterface<array<string, mixed>> */
    public function review(DocumentExtraction $extraction, string $action): FormInterface
    {
        $builder = $this->forms->createNamedBuilder('ocr_review', FormType::class, ['fiche_version' => $extraction->fiche()->version()], [
            'action' => $action, 'method' => 'POST', 'csrf_token_id' => 'ocr-review-'.$extraction->id(), 'attr' => ['data-controller' => 'ocr-review'],
        ])->add('fiche_version', HiddenType::class);
        foreach ($extraction->suggestions() as $suggestion) {
            if (!$suggestion->isPending()) { continue; }
            $value = $suggestion->correctedValue();
            if (is_array($value)) { $value = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
            $row = $builder->create($suggestion->id(), FormType::class, ['data_class' => null, 'label' => false])
                ->add('value', TextareaType::class, ['label' => false, 'data' => $value, 'attr' => ['rows' => 'table' === $suggestion->valueType() ? 8 : 3]])
                ->add('accept', CheckboxType::class, ['label' => 'Valider', 'required' => false, 'attr' => ['data-action' => 'ocr-review#toggle', 'data-ocr-choice' => 'accept', 'data-ocr-opposite' => 'reject']])
                ->add('reject', CheckboxType::class, ['label' => 'Refuser', 'required' => false, 'attr' => ['data-action' => 'ocr-review#toggle', 'data-ocr-choice' => 'reject', 'data-ocr-opposite' => 'accept']]);
            $builder->add($row);
        }
        $builder->add('save', SubmitType::class, ['label' => 'Sauvegarder']);
        return $builder->getForm();
    }

    /** @return FormInterface<mixed> */
    public function retry(DocumentExtraction $extraction, string $action): FormInterface
    {
        return $this->forms->createNamed('ocr_retry', ActionType::class, null, ['action' => $action, 'method' => 'POST', 'csrf_token_id' => 'ocr-retry-'.$extraction->id(), 'button_label' => 'Créer une nouvelle analyse']);
    }
}
