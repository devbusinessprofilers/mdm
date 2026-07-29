<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Lov\LieuLovCatalog;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Lieu> */
final class LieuType extends AbstractType
{
    /** @param FormBuilderInterface<Lieu|null> $builder */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', IntegerType::class, $this->field('Code', 'code', 'changeCode', false))
            ->add('label', TextType::class, $this->field('Libellé', 'label', 'changeLabel'))
            ->add('generaleTypologie', ChoiceType::class, $this->field('Typologie', 'generaleTypologie', 'changeGeneraleTypologie', false) + [
                'choices' => array_flip(LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE')),
                'multiple' => true,
            ])
            ->add('generaleWebsiteUrl', TextType::class, $this->field('Site web', 'generaleWebsiteUrl', 'changeGeneraleWebsiteUrl', false))
            ->add('published', CheckboxType::class, $this->field('Publié', 'published', 'changePublished', false))
            ->add('localisation', LocalisationType::class, [
                'label' => false,
                'required' => false,
                'empty_data' => static fn (): Localisation => new Localisation(),
                'getter' => static fn (Lieu $lieu): ?Localisation => $lieu->localisation(),
                'setter' => static function (Lieu &$lieu, ?Localisation $value): void { $lieu->changeLocalisation($value); },
            ]);

        $this->collection($builder, 'salles', SalleType::class, 'salles', 'addSalle', 'removeSalle');
        $this->collection($builder, 'periodesFermeture', PeriodeFermetureType::class, 'periodesFermeture', 'addPeriodeFermeture', 'removePeriodeFermeture');
        $this->collection($builder, 'acces', AccesLieuType::class, 'acces', 'addAcces', 'removeAcces');
        $this->collection($builder, 'ressources', RessourceLieuType::class, 'ressources', 'addRessource', 'removeRessource');
    }

    /** @return array<string, mixed> */
    private function field(string $label, string $getter, string $setter, bool $required = true): array
    {
        return [
            'label' => $label,
            'required' => $required,
            'getter' => static fn (Lieu $lieu): mixed => $lieu->{$getter}(),
            'setter' => static function (Lieu &$lieu, mixed $value) use ($setter): void { $lieu->{$setter}($value); },
        ];
    }

    /**
     * @param FormBuilderInterface<Lieu|null> $builder
     * @param class-string<FormTypeInterface<mixed>> $entryType
     */
    private function collection(FormBuilderInterface $builder, string $name, string $entryType, string $getter, string $adder, string $remover): void
    {
        $builder->add($name, CollectionType::class, [
            'entry_type' => $entryType,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'prototype' => true,
            'getter' => static fn (Lieu $lieu): Collection => $lieu->{$getter}(),
            'setter' => static function (Lieu &$lieu, iterable $submitted) use ($getter, $adder, $remover): void {
                $submitted = is_array($submitted) ? $submitted : iterator_to_array($submitted);
                foreach ($lieu->{$getter}()->toArray() as $existing) {
                    if (!in_array($existing, $submitted, true)) {
                        $lieu->{$remover}($existing);
                    }
                }
                foreach ($submitted as $item) {
                    $lieu->{$adder}($item);
                }
            },
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Lieu::class]);
    }
}
