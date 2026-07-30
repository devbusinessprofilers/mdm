<?php

declare(strict_types=1);

namespace App\Pim\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

/** @extends AbstractType<list<string>> */
final class StringListType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?array $values): string => implode("\n", $values ?? []),
            static fn (?string $value): array => array_values(array_filter(array_map(
                static fn (string $item): string => trim($item),
                preg_split('/\R/', $value ?? '') ?: [],
            ), static fn (string $item): bool => '' !== $item)),
        ));
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }
}
