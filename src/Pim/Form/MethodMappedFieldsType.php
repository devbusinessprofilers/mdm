<?php

declare(strict_types=1);

namespace App\Pim\Form;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<object> */
final class MethodMappedFieldsType extends AbstractType
{
    public function __construct(private readonly ManagerRegistry $doctrine)
    {
    }

    /** @param FormBuilderInterface<object|null> $builder */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var class-string $mappedClass */
        $mappedClass = $options['mapped_class'];
        $manager = $this->doctrine->getManagerForClass($mappedClass);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('Aucun EntityManager ORM pour %s.', $mappedClass));
        }
        $metadata = $manager->getClassMetadata($mappedClass);

        /** @var array<string, array<string, mixed>> $fields */
        $fields = $options['fields'];
        foreach ($fields as $name => $definition) {
            $doctrineType = $metadata->hasField($name) ? $metadata->getTypeOfField($name) : null;
            $fieldType = $definition['type'] ?? null;
            if (!is_string($fieldType)) {
                $fieldType = $this->inferType($doctrineType, $name);
            }
            /** @var class-string<FormTypeInterface<mixed>> $fieldType */
            $setter = 'change'.ucfirst($name);
            $fieldOptions = $definition['options'] ?? [];
            if ('decimal' === $doctrineType) {
                // grouping : le masque du MoneyInput sépare les milliers par
                // une espace, le transformer doit l'accepter en retour.
                $fieldOptions += ['input' => 'string', 'scale' => 2, 'grouping' => true];
            }
            $fieldOptions += [
                'label' => $definition['label'] ?? null,
                'required' => $definition['required'] ?? false,
                'getter' => static fn (object $data): mixed => $data->{$name}(),
                'setter' => static function (object &$data, mixed $value) use ($setter): void { $data->{$setter}($value); },
            ];
            $builder->add($name, $fieldType, $fieldOptions);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
        $resolver->setRequired(['mapped_class', 'fields']);
        $resolver->setAllowedTypes('mapped_class', 'string');
        $resolver->setAllowedTypes('fields', 'array');
    }

    /** @return class-string<FormTypeInterface<mixed>> */
    private function inferType(?string $doctrineType, string $name): string
    {
        if (str_contains(strtolower($name), 'email')) {
            return EmailType::class;
        }
        if (str_contains(strtolower($name), 'url') || str_contains(strtolower($name), 'youtube')) {
            return UrlType::class;
        }

        return match ($doctrineType) {
            'boolean' => CheckboxType::class,
            'integer', 'smallint', 'bigint' => IntegerType::class,
            // Les décimaux du PIM sont des montants (grilles tarifaires) :
            // MoneyType → composant MoneyInput, l'icône € dans le champ.
            'decimal', 'float' => MoneyType::class,
            'text' => TextareaType::class,
            'json' => StringListType::class,
            default => TextType::class,
        };
    }
}
