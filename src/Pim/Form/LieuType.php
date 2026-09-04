<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Etl\Repository\FicheSalesforceRepository;
use App\Pim\Entity\FicheAdministratif;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\LieuTarification;
use App\Pim\Entity\Localisation;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\Service\LieuObligationsPublication;
use App\Pim\Validation\ValidationGroups;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Lieu> */
final class LieuType extends AbstractType
{
    public function __construct(private readonly FicheSalesforceRepository $salesforce)
    {
    }

    /** @param FormBuilderInterface<Lieu|null> $builder */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Fiche connue de Salesforce : le statut partenaire est écrasé à
        // chaque refresh, la case devient consultative.
        $data = $options['data'] ?? null;
        $partenaireGereParSf = $data instanceof Lieu
            && $this->salesforce->existePourFiche($data->fiche()->id());
        $builder
            ->add('label', TextType::class, $this->field('Nom du lieu', 'label', 'changeLabel'))
            ->add('businessPremium', CheckboxType::class, [
                'label' => 'Adhérent Business Premium',
                'required' => false,
                'getter' => static fn (Lieu $lieu): bool => $lieu->fiche()->businessPremium(),
                'setter' => static function (Lieu &$lieu, mixed $value): void { $lieu->fiche()->changeBusinessPremium((bool) $value); },
            ])
            ->add('partenaireBp', CheckboxType::class, [
                'label' => 'Partenaire BP',
                'required' => false,
                'disabled' => $partenaireGereParSf,
                'help' => $partenaireGereParSf ? 'Géré par Salesforce.' : null,
                // Chrome « autorité » : seul champ réellement piloté par Salesforce (défaut MDM ailleurs).
                'label_attr' => $partenaireGereParSf ? ['data-autorite' => \App\Pim\Enum\Autorite::Salesforce->value] : [],
                'getter' => static fn (Lieu $lieu): bool => $lieu->fiche()->partenaireBp(),
                'setter' => static function (Lieu &$lieu, mixed $value): void { $lieu->fiche()->changePartenaireBp((bool) $value); },
            ])
            ->add('generaleTypologie', ChoiceType::class, $this->field('Typologie', 'generaleTypologie', 'changeGeneraleTypologie', false) + [
                'choices' => array_flip(LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE')),
                'multiple' => true,
            ])
            ->add('generaleWebsiteUrl', UrlType::class, $this->field('Site web officiel', 'generaleWebsiteUrl', 'changeGeneraleWebsiteUrl', false) + [
                'constraints' => [new \Symfony\Component\Validator\Constraints\Length(max: Lieu::WEBSITE_MAX_LENGTH), new \Symfony\Component\Validator\Constraints\Url(requireTld: true)],
            ])
            ->add('restaurant', RestaurantAutocompleteType::class, $this->field('Restaurant associé', 'restaurant', 'changeRestaurant', false))
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
                'mapped_class' => FicheAdministratif::class,
                'data_class' => FicheAdministratif::class,
                'fields' => FicheFormCatalog::administrative(),
                'getter' => static fn (Lieu $lieu): FicheAdministratif => $lieu->administratif(),
                'setter' => static function (Lieu &$lieu, FicheAdministratif $value): void {},
            ])
            ->add('tarification', MethodMappedFieldsType::class, [
                'mapped_class' => LieuTarification::class,
                'data_class' => LieuTarification::class,
                'fields' => LieuFormCatalog::pricing(),
                'getter' => static fn (Lieu $lieu): LieuTarification => $lieu->tarification(),
                'setter' => static function (Lieu &$lieu, LieuTarification $value): void {},
            ]);

        $this->collection($builder, 'salles', SalleType::class, 'salles', 'addSalle', 'removeSalle', label: 'Salles');
        $this->collection($builder, 'periodesFermeture', PeriodeFermetureType::class, 'periodesFermeture', 'addPeriodeFermeture', 'removePeriodeFermeture', label: 'Périodes de fermeture');
        $this->collection($builder, 'acces', AccesLieuType::class, 'acces', 'addAcces', 'removeAcces', label: 'Accès', help: 'Au moins un accès de type aéroport et un accès de type gare sont requis pour la publication.');
        $this->collection($builder, 'ressources', RessourceLieuType::class, 'ressources', 'addRessource', 'removeRessource', [
            'salle_choices' => $options['data'] instanceof Lieu ? $options['data']->salles()->toArray() : [],
        ]);
        FicheFormCatalog::ajouterFichiers($builder);
        $builder->add('submit', SubmitType::class, ['label' => 'Enregistrer']);
        // Bouton « Oui, dépublier » de la modale de l'éditeur : seul ce
        // submitter l'envoie (isClicked), une soumission ordinaire l'ignore.
        $builder->add('confirmerDepublication', SubmitType::class, ['label' => 'Oui, dépublier']);

        // Capacité d'accueil par défaut (bible row 52) : nombre total de
        // chambres + chambres twin, calculée seulement quand la capacité est
        // laissée vide — une saisie manuelle n'est jamais écrasée.
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $lieu = $event->getData();
            if (!$lieu instanceof Lieu || !$lieu->chambreHebergement() || null !== $lieu->chambreCapaciteTotale() || null === $lieu->chambreNbTotal()) {
                return;
            }
            $lieu->changeChambreCapaciteTotale($lieu->chambreNbTotal() + ($lieu->chambreNbTotalTwin() ?? 0));
        });
    }

    /**
     * Astérisque permanent des champs obligatoires de la bible : un marqueur
     * d'affichage (var `obligatoire`, lue par le thème de l'éditeur), jamais
     * l'option `required` — l'attribut HTML bloquerait l'enregistrement des
     * brouillons et le cas « champ vidé » que l'éditeur doit pouvoir soumettre.
     *
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        foreach (LieuObligationsPublication::cheminsFormulaire() as $chemin) {
            $cible = $view;
            foreach (explode('.', $chemin) as $segment) {
                if (!isset($cible->children[$segment])) {
                    continue 2;
                }
                $cible = $cible->children[$segment];
            }
            $cible->vars['obligatoire'] = true;
        }
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
     *
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
     * @param FormBuilderInterface<Lieu|null>        $builder
     * @param class-string<FormTypeInterface<mixed>> $entryType
     * @param array<string, mixed>                   $entryOptions
     */
    private function collection(FormBuilderInterface $builder, string $name, string $entryType, string $getter, string $adder, string $remover, array $entryOptions = [], ?string $label = null, ?string $help = null): void
    {
        $builder->add($name, CollectionType::class, [
            'label' => $label,
            'help' => $help,
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
            'validation_groups' => [ValidationGroups::DRAFT],
        ]);
    }
}
