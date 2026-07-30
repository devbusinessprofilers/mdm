<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\LieuAdministratif;
use App\Pim\Entity\Lieu\LieuTarification;
use App\Pim\Entity\Localisation;
use App\Pim\Lov\LieuLovCatalog;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
            ->add('generaleWebsiteUrl', UrlType::class, $this->field('Site web officiel', 'generaleWebsiteUrl', 'changeGeneraleWebsiteUrl', false) + [
                'constraints' => [new \Symfony\Component\Validator\Constraints\Length(max: 100), new \Symfony\Component\Validator\Constraints\Url(requireTld: true)],
            ])
            ->add('informationsGenerales', MethodMappedFieldsType::class, $this->section(LieuFormCatalog::general()))
            ->add('disponibilites', MethodMappedFieldsType::class, $this->section(LieuFormCatalog::availability()))
            ->add('accessibiliteDescription', MethodMappedFieldsType::class, $this->section(LieuFormCatalog::accessibilityAndDescription()))
            ->add('hebergement', MethodMappedFieldsType::class, $this->section(LieuFormCatalog::accommodation()))
            ->add('syntheseSalles', MethodMappedFieldsType::class, $this->section(LieuFormCatalog::meetingRooms()))
            ->add('equipementsServices', MethodMappedFieldsType::class, $this->section(LieuFormCatalog::equipmentAndServices()))
            ->add('rse', MethodMappedFieldsType::class, $this->section(LieuFormCatalog::rse()))
            ->add('loisirs', MethodMappedFieldsType::class, $this->section(LieuFormCatalog::leisure()))
            ->add('restauration', MethodMappedFieldsType::class, $this->section(LieuFormCatalog::restaurant()))
            ->add('visibilite', MethodMappedFieldsType::class, $this->section(LieuFormCatalog::visibility()))
            ->add('localisation', LocalisationType::class, [
                'label' => false,
                'required' => false,
                'empty_data' => static fn (): Localisation => new Localisation(),
                'getter' => static fn (Lieu $lieu): ?Localisation => $lieu->localisation(),
                'setter' => static function (Lieu &$lieu, ?Localisation $value): void { $lieu->changeLocalisation($value); },
            ])
            ->add('administratif', MethodMappedFieldsType::class, [
                'mapped_class' => LieuAdministratif::class,
                'data_class' => LieuAdministratif::class,
                'fields' => LieuFormCatalog::administrative(),
                'getter' => static fn (Lieu $lieu): LieuAdministratif => $lieu->administratif(),
                'setter' => static function (Lieu &$lieu, LieuAdministratif $value): void {},
            ])
            ->add('tarification', MethodMappedFieldsType::class, [
                'mapped_class' => LieuTarification::class,
                'data_class' => LieuTarification::class,
                'fields' => LieuFormCatalog::pricing(),
                'getter' => static fn (Lieu $lieu): LieuTarification => $lieu->tarification(),
                'setter' => static function (Lieu &$lieu, LieuTarification $value): void {},
            ]);

        $this->collection($builder, 'salles', SalleType::class, 'salles', 'addSalle', 'removeSalle');
        $this->collection($builder, 'periodesFermeture', PeriodeFermetureType::class, 'periodesFermeture', 'addPeriodeFermeture', 'removePeriodeFermeture');
        $this->collection($builder, 'acces', AccesLieuType::class, 'acces', 'addAcces', 'removeAcces');
        $this->collection($builder, 'ressources', RessourceLieuType::class, 'ressources', 'addRessource', 'removeRessource', [
            'salle_choices' => $options['data'] instanceof Lieu ? $options['data']->salles()->toArray() : [],
        ]);
        $builder->add('submit', SubmitType::class, ['label' => 'Enregistrer']);
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
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    private function section(array $fields): array
    {
        return [
            'inherit_data' => true,
            'mapped_class' => Lieu::class,
            'fields' => $fields,
        ];
    }

    /**
     * @param FormBuilderInterface<Lieu|null> $builder
     * @param class-string<FormTypeInterface<mixed>> $entryType
     * @param array<string, mixed> $entryOptions
     */
    private function collection(FormBuilderInterface $builder, string $name, string $entryType, string $getter, string $adder, string $remover, array $entryOptions = []): void
    {
        $builder->add($name, CollectionType::class, [
            'entry_type' => $entryType,
            'entry_options' => $entryOptions,
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
        $resolver->setDefaults([
            'data_class' => Lieu::class,
            'constraints' => [new Callback(static function (Lieu $lieu, ExecutionContextInterface $context): void {
                $openingHour = $lieu->dispoHeureOuvertureHeure();
                $closingHour = $lieu->dispoHeureFermetureHeure();
                if (null !== $openingHour && null !== $closingHour) {
                    $opening = 60 * $openingHour + ($lieu->dispoHeureOuvertureMinutes() ?? 0);
                    $closing = 60 * $closingHour + ($lieu->dispoHeureFermetureMinutes() ?? 0);
                    if ($closing <= $opening) {
                        $context->buildViolation("L'heure de fermeture doit être postérieure à l'heure d'ouverture.")
                            ->atPath('disponibilites.dispoHeureFermetureHeure')
                            ->addViolation();
                    }
                }

                $photos = array_values(array_filter(
                    $lieu->ressources()->toArray(),
                    static fn ($resource): bool => \App\Pim\Enum\NatureRessource::Photo === $resource->nature(),
                ));
                if (count($photos) > 25) {
                    $context->buildViolation('Un lieu ne peut pas contenir plus de 25 photos.')
                        ->atPath('ressources')
                        ->addViolation();
                }
                if (1 < count(array_filter($photos, static fn ($resource): bool => 'PHOTO_PRINCIPALE' === $resource->usage()))) {
                    $context->buildViolation('Un lieu ne peut avoir qu’une seule photo principale.')
                        ->atPath('ressources')
                        ->addViolation();
                }
            })],
        ]);
    }
}
