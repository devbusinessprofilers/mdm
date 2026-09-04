<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Etl\Repository\FicheSalesforceRepository;
use App\Pim\Entity\FicheAdministratif;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\NatureRessource;
use App\Pim\Lov\RestaurantLovCatalog;
use App\Pim\Service\RestaurantObligationsPublication;
use App\Pim\Validation\LienVideo;
use App\Pim\Validation\ValidationGroups;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

/** @extends AbstractType<Restaurant> */
final class RestaurantType extends AbstractType
{
    /** Les six tarifs de l'onglet Tarifs : nom => [libellé maquette, setter]. */
    public const TARIFS = [
        'tarifDejeunerAssis' => ['Déjeuner assis', 'changeTarifDejeunerAssis'],
        'tarifCocktailDejeunatoire' => ['Cocktail déjeunatoire 10 pers', 'changeTarifCocktailDejeunatoire'],
        'tarifDinerAssis' => ['Diner assis', 'changeTarifDinerAssis'],
        'tarifCocktailDinatoire' => ['Cocktail dînatoire 10 pers', 'changeTarifCocktailDinatoire'],
        'tarifForfaitVin' => ['Forfait vin', 'changeTarifForfaitVin'],
        'tarifForfaitAlcool' => ['Forfait alcool (apéritif digestif)', 'changeTarifForfaitAlcool'],
    ];

    public function __construct(private readonly FicheSalesforceRepository $salesforce)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Fiche connue de Salesforce : le statut partenaire est écrasé à
        // chaque refresh, la case devient consultative.
        $data = $options['data'] ?? null;
        $partenaireGereParSf = $data instanceof Restaurant
            && $this->salesforce->existePourFiche($data->fiche()->id());
        $builder
            ->add(
                'label',
                TextType::class,
                $this->field('Nom du restaurant', 'label', 'changeLabel'),
            )
            ->add(
                'lieu',
                LieuAutocompleteType::class,
                $this->field('Lieu associé', 'lieu', 'changeLieu'),
            )
            ->add('businessPremium', OuiNonType::class, [
                'label' => 'Adhérent Business Premium',
                'required' => false,
                'getter' => static fn (Restaurant $restaurant): bool => $restaurant->fiche()->businessPremium(),
                'setter' => static function (Restaurant &$restaurant, mixed $value): void { $restaurant->fiche()->changeBusinessPremium((bool) $value); },
            ])
            ->add('partenaireBp', OuiNonType::class, [
                'label' => 'Partenaire BP',
                'required' => false,
                'disabled' => $partenaireGereParSf,
                'help' => $partenaireGereParSf ? 'Géré par Salesforce.' : null,
                // Chrome « autorité » : seul champ réellement piloté par Salesforce (défaut MDM ailleurs).
                'label_attr' => $partenaireGereParSf ? ['data-autorite' => \App\Pim\Enum\Autorite::Salesforce->value] : [],
                'getter' => static fn (Restaurant $restaurant): bool => $restaurant->fiche()->partenaireBp(),
                'setter' => static function (Restaurant &$restaurant, mixed $value): void { $restaurant->fiche()->changePartenaireBp((bool) $value); },
            ]);

        $this->selection(
            $builder,
            'typesRestaurant',
            'Typologie de restaurant',
            'TYPE_RESTAURANT',
            'changeTypesRestaurant',
        );
        $this->selection(
            $builder,
            'typesCuisine',
            'Type de cuisine',
            'TYPE_CUISINE',
            'changeTypesCuisine',
        );
        $this->selection(
            $builder,
            'specificitesAlimentaires',
            'Spécificité alimentaire',
            'SPECIFICITE_ALIMENTAIRE',
            'changeSpecificitesAlimentaires',
        );
        $this->selection(
            $builder,
            'typesEvenement',
            "Type d'évènement",
            'TYPE_EVENEMENT',
            'changeTypesEvenement',
        );

        $builder
            ->add(
                'siteOfficiel',
                UrlType::class,
                $this->field(
                    'Site officiel',
                    'siteOfficiel',
                    'changeSiteOfficiel',
                ),
            )
            ->add(
                'privatisationTotale',
                OuiNonType::class,
                $this->field(
                    'Privatisation totale du restaurant',
                    'privatisationTotale',
                    'changePrivatisationTotale',
                ),
            )
            ->add(
                'privatisationPartielle',
                OuiNonType::class,
                $this->field(
                    'Privatisation partielle (salon, étage, terrasse)',
                    'privatisationPartielle',
                    'changePrivatisationPartielle',
                ),
            );

        $this->selection(
            $builder,
            'joursOuverture',
            "Jours d'ouverture",
            'DISPO_JOUR_OUVERTURE',
            'changeJoursOuverture',
            true,
        );

        $builder
            ->add(
                'horairesJours',
                HorairesJoursType::class,
                [
                    'label' => false,
                    'jours' => RestaurantLovCatalog::values('DISPO_JOUR_OUVERTURE'),
                ] + $this->field(
                    "Horaires d'ouverture",
                    'horairesJours',
                    'changeHorairesJours',
                ),
            )
            ->add('periodesFermeture', CollectionType::class, [
                'label' => 'Périodes de fermeture',
                'entry_type' => RestaurantPeriodeFermetureType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ] + $this->collectionAccessors(
                'periodesFermeture',
                'addPeriodeFermeture',
                'removePeriodeFermeture',
            ))
            ->add('localisation', LocalisationType::class, [
                'label' => 'Localisation',
                'required' => false,
                'empty_data' => static fn (): Localisation => new Localisation(),
                'getter' => static fn (
                    Restaurant $restaurant,
                ): ?Localisation => $restaurant->localisation(),
                'setter' => static function (
                    Restaurant &$restaurant,
                    ?Localisation $location,
                ): void {
                    $restaurant->changeLocalisation($location);
                },
            ])
            ->add('acces', CollectionType::class, [
                'label' => 'Accès',
                'help' => 'Ajouter jusqu\'à 3 options par catégorie de transports. Au moins un aéroport et une gare sont requis pour la publication.',
                'entry_type' => RestaurantAccesType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ] + $this->collectionAccessors('acces', 'addAcces', 'removeAcces'))
            ->add(
                'accesPmr',
                OuiNonType::class,
                $this->field(
                    'Accès PMR',
                    'accesPmr',
                    'changeAccesPmr',
                ),
            )
            ->add(
                'toilettesPmr',
                OuiNonType::class,
                $this->field(
                    'Toilettes adaptées aux PMR',
                    'toilettesPmr',
                    'changeToilettesPmr',
                ),
            )
            ->add(
                'descriptionGenerale',
                TextareaType::class,
                $this->field(
                    'Description générale',
                    'descriptionGenerale',
                    'changeDescriptionGenerale',
                ) + ['attr' => ['data-suggerer' => true]],
            )
            ->add(
                'atouts',
                ListeIndexeeType::class,
                $this->field(
                    'Atouts',
                    'atouts',
                    'changeAtouts',
                ) + ['label' => false, 'nombre' => 5, 'libelle_format' => 'Les plus / atouts %d', 'entry_attr' => ['maxlength' => 255]],
            );

        foreach (
            [
                'capaciteAssiseMax' => [
                    'Capacité assise maximum du restaurant (couverts)',
                    'changeCapaciteAssiseMax',
                ],
                'capaciteEspacePrivatisable' => [
                    'Capacité en salle privatisable ou espace clos',
                    'changeCapaciteEspacePrivatisable',
                ],
                'capaciteBanquet' => [
                    'Capacité banquet (repas assis festif)',
                    'changeCapaciteBanquet',
                ],
                'capaciteCocktail' => [
                    'Capacité cocktail (maximum debout)',
                    'changeCapaciteCocktail',
                ],
            ] as $name => [$label, $setter]
        ) {
            $builder->add(
                $name,
                IntegerType::class,
                $this->field($label, $name, $setter),
            );
        }

        // Onglet Tarifs (maquette) : montants HT « à partir de », null =
        // prestation non proposée (interrupteur du partial _tarifs_restaurant).
        foreach (self::TARIFS as $name => [$label, $setter]) {
            $builder->add(
                $name,
                MoneyType::class,
                $this->field($label, $name, $setter) + ['currency' => 'EUR', 'input' => 'string', 'scale' => 2],
            );
        }

        $builder->add('salles', CollectionType::class, [
            'label' => 'Salles et espaces privatisables',
            'entry_type' => RestaurantSalleType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'prototype' => true,
        ] + $this->collectionAccessors('salles', 'addSalle', 'removeSalle'));

        $this->selection(
            $builder,
            'services',
            'Services',
            'SERVICE_RESTAURANT',
            'changeServices',
        );
        $this->selection(
            $builder,
            'equipements',
            'Équipements',
            'EQUIPEMENT_RESTAURANT',
            'changeEquipements',
        );
        $this->selection(
            $builder,
            'engagementsRse',
            'Engagements RSE',
            'ENGAGEMENT_RSE_RESTAURANT',
            'changeEngagementsRse',
        );

        $builder
            ->add(
                'youtubeUrl',
                UrlType::class,
                $this->field(
                    'Lien vidéo',
                    'youtubeUrl',
                    'changeYoutubeUrl',
                ) + ['constraints' => [new LienVideo()]],
            )
            ->add('ressources', CollectionType::class, [
                'label' => 'Photos',
                'entry_type' => RestaurantRessourceType::class,
                'entry_options' => ['restaurant' => $options['data']],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'getter' => static fn (
                    Restaurant $restaurant,
                ): Collection => new ArrayCollection(
                    array_values(
                        array_filter(
                            $restaurant->ressources()->toArray(),
                            static fn (
                                RessourceLieu $resource,
                            ): bool => NatureRessource::Photo === $resource->nature(),
                        ),
                    ),
                ),
                'setter' => static function (
                    Restaurant &$restaurant,
                    iterable $submitted,
                ): void {
                    $submitted = is_array($submitted)
                        ? $submitted
                        : iterator_to_array($submitted);

                    foreach ($restaurant->ressources()->toArray() as $old) {
                        if (
                            NatureRessource::Photo === $old->nature()
                            && !in_array($old, $submitted, true)
                        ) {
                            $restaurant->removeRessource($old);
                        }
                    }

                    foreach ($submitted as $resource) {
                        if ($resource instanceof RessourceLieu) {
                            $restaurant->addRessource($resource);
                        }
                    }
                },
            ])
            ->add('menus', FileType::class, [
                'label' => 'Menus',
                'help' => 'PDF, JPEG ou PNG.',
                'mapped' => false,
                'multiple' => true,
                'required' => false,
                'attr' => ['data-dropzone' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'data-max-files' => 10, 'data-max-size' => '10M'],
                'constraints' => [new All([new File(maxSize: '10M')])],
            ])
            ->add('supportsCommerciaux', FileType::class, [
                'label' => 'Supports commerciaux',
                'help' => 'PDF, JPEG ou PNG.',
                'mapped' => false,
                'multiple' => true,
                'required' => false,
                'attr' => ['data-dropzone' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'data-max-files' => 10, 'data-max-size' => '100M'],
                'constraints' => [new All([new File(maxSize: '100M')])],
            ])
            ->add('documentTitle', TextType::class, [
                'help' => 'Appliqué aux fichiers déposés ci-dessus, à l’enregistrement.',
                'label' => 'Titre des nouveaux documents',
                'mapped' => false,
                'required' => false,
            ])
            ->add('documentSource', TextType::class, [
                'label' => 'Source des nouveaux documents',
                'mapped' => false,
                'required' => false,
            ]);

        // Facturation & partenariat (maquette portail) : bloc commun porté par la fiche.
        $builder->add('administratif', MethodMappedFieldsType::class, [
            'mapped_class' => FicheAdministratif::class,
            'data_class' => FicheAdministratif::class,
            'fields' => FicheFormCatalog::administrative(),
            'getter' => static fn (Restaurant $entite): FicheAdministratif => $entite->administratif(),
            'setter' => static function (Restaurant &$entite, FicheAdministratif $value): void {},
        ]);
        FicheFormCatalog::ajouterFichiers($builder);
        $builder->add('submit', SubmitType::class, ['label' => 'Enregistrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Restaurant::class,
            'validation_groups' => [ValidationGroups::DRAFT],
        ]);
    }

    /**
     * Astérisque permanent des champs bloquants à la soumission (marqueur
     * `obligatoire` lu par le thème de l'éditeur, jamais l'option `required`).
     *
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        RestaurantObligationsPublication::marquer($view);
    }

    /** @param FormBuilderInterface<Restaurant|null> $builder */
    private function selection(
        FormBuilderInterface $builder,
        string $name,
        string $label,
        string $attribute,
        string $setter,
        bool $expanded = false,
    ): void {
        $builder->add(
            $name,
            ChoiceType::class,
            $this->field($label, $name, $setter) + [
                'choices' => array_flip(RestaurantLovCatalog::values($attribute)),
                'multiple' => true,
                'expanded' => $expanded,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function collectionAccessors(
        string $getter,
        string $adder,
        string $remover,
    ): array {
        return [
            'getter' => static fn (
                Restaurant $restaurant,
            ): Collection => $restaurant->{$getter}(),
            'setter' => static function (
                Restaurant &$restaurant,
                iterable $submitted,
            ) use ($getter, $adder, $remover): void {
                $submitted = is_array($submitted)
                    ? $submitted
                    : iterator_to_array($submitted);

                foreach ($restaurant->{$getter}()->toArray() as $existing) {
                    if (!in_array($existing, $submitted, true)) {
                        $restaurant->{$remover}($existing);
                    }
                }

                foreach ($submitted as $item) {
                    $restaurant->{$adder}($item);
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    private function field(
        string $label,
        string $getter,
        string $setter,
    ): array {
        return [
            'label' => $label,
            'required' => false,
            'getter' => static fn (
                Restaurant $restaurant,
            ): mixed => $restaurant->{$getter}(),
            'setter' => static function (
                Restaurant &$restaurant,
                mixed $value,
            ) use ($setter): void {
                $restaurant->{$setter}($value);
            },
        ];
    }
}
