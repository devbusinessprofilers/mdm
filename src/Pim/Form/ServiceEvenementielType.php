<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Etl\Repository\FicheSalesforceRepository;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\FicheAdministratif;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\ModeInterventionService;
use App\Pim\Enum\NatureRessource;
use App\Pim\Lov\ServiceLovCatalog;
use App\Pim\Service\ServiceEvenementielObligationsPublication;
use App\Pim\Validation\LienVideo;
use App\Pim\Validation\ValidationGroups;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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

/** @extends AbstractType<ServiceEvenementiel> */
final class ServiceEvenementielType extends AbstractType
{
    public function __construct(private readonly FicheSalesforceRepository $salesforce)
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        // Fiche connue de Salesforce : le statut partenaire est écrasé à
        // chaque refresh, la case devient consultative.
        $data = $options['data'] ?? null;
        $partenaireGereParSf = $data instanceof ServiceEvenementiel
            && $this->salesforce->existePourFiche($data->fiche()->id());
        $builder
            ->add(
                "label",
                TextType::class,
                $this->field("Nom du prestataire", "label", "changeLabel"),
            )
            ->add("businessPremium", CheckboxType::class, [
                "label" => "Adhérent Business Premium",
                "required" => false,
                "getter" => static fn (ServiceEvenementiel $service): bool => $service->fiche()->businessPremium(),
                "setter" => static function (ServiceEvenementiel &$service, mixed $value): void { $service->fiche()->changeBusinessPremium((bool) $value); },
            ])
            ->add("partenaireBp", CheckboxType::class, [
                "label" => "Partenaire BP",
                "required" => false,
                "disabled" => $partenaireGereParSf,
                "help" => $partenaireGereParSf ? "Géré par Salesforce." : null,
                // Chrome « autorité » : seul champ réellement piloté par Salesforce (défaut MDM ailleurs).
                "label_attr" => $partenaireGereParSf ? ["data-autorite" => \App\Pim\Enum\Autorite::Salesforce->value] : [],
                "getter" => static fn (ServiceEvenementiel $service): bool => $service->fiche()->partenaireBp(),
                "setter" => static function (ServiceEvenementiel &$service, mixed $value): void { $service->fiche()->changePartenaireBp((bool) $value); },
            ])
            ->add(
                "prestations",
                ChoiceType::class,
                $this->field(
                    "Prestations",
                    "prestations",
                    "changePrestations",
                ) + [
                    "choices" => array_flip(ServiceLovCatalog::prestations()),
                    "multiple" => true,
                    // Select multiple (composant Select) — plus de cases filtrées.
                    "expanded" => false,
                ],
            );

        // Un select de sous-prestations par famille — tous visibles dans
        // l'onglet Prestations, libellé = la famille (maquette).
        foreach (ServiceLovCatalog::sousPrestationAttributes() as $attribute => $famille) {
            $builder->add(
                ServiceLovCatalog::SOUS_PRESTATION_FIELDS[$attribute],
                ChoiceType::class,
                [
                    "label" => $famille,
                    "required" => false,
                    "getter" => static fn (
                        ServiceEvenementiel $service,
                    ): array => $service->sousPrestationsPour($attribute),
                    "setter" => static function (
                        ServiceEvenementiel &$service,
                        mixed $value,
                    ) use ($attribute): void {
                        $service->changeSousPrestationsPour(
                            $attribute,
                            array_values(array_filter((array) $value, is_string(...))),
                        );
                    },
                    "choices" => array_flip(
                        ServiceLovCatalog::sousPrestationsFor($attribute),
                    ),
                    "multiple" => true,
                    "expanded" => false,
                ],
            );
        }

        $builder
            ->add(
                "descriptionGenerale",
                TextareaType::class,
                $this->field(
                    "Description générale",
                    "descriptionGenerale",
                    "changeDescriptionGenerale",
                ) + ["attr" => ["data-suggerer" => true]],
            );

        // Oui / Non maquette : radios, « Non » par défaut (OuiNonType).
        foreach ($this->booleanFields() as $name => [$label, $setter]) {
            $builder->add($name, OuiNonType::class, $this->field($label, $name, $setter));
        }

        $builder
            ->add(
                "modeIntervention",
                ChoiceType::class,
                $this->field(
                    "Rayon d’action",
                    "modeIntervention",
                    "changeModeIntervention",
                ) + [
                    "choices" => [
                        "Localisation fixe" => ModeInterventionService::Fixe,
                        "Localisation mobile" =>
                            ModeInterventionService::Mobile,
                    ],
                    "choice_value" => static fn(
                        ?ModeInterventionService $mode,
                    ): ?string => $mode?->value,
                    "placeholder" => "Choisir…",
                ],
            )
            ->add("acces", CollectionType::class, [
                "label" => "Accès",
                "help" => "Ajouter jusqu'à 3 options par catégorie de transports.",
                "entry_type" => ServiceAccesType::class,
                "allow_add" => true,
                "allow_delete" => true,
                "by_reference" => false,
                "prototype" => true,
                "getter" => static fn (ServiceEvenementiel $service): Collection => $service->acces(),
                "setter" => static function (ServiceEvenementiel &$service, iterable $submitted): void {
                    $submitted = is_array($submitted) ? $submitted : iterator_to_array($submitted);
                    foreach ($service->acces()->toArray() as $existing) {
                        if (!in_array($existing, $submitted, true)) {
                            $service->removeAcces($existing);
                        }
                    }
                    foreach ($submitted as $item) {
                        $service->addAcces($item);
                    }
                },
            ])
            ->add("localisation", LocalisationType::class, [
                "label" => "Localisation fixe",
                "required" => false,
                "empty_data" => static fn(): Localisation => new Localisation(),
                "getter" => static fn(
                    ServiceEvenementiel $service,
                ): ?Localisation => $service->localisation(),
                "setter" => static function (
                    ServiceEvenementiel &$service,
                    ?Localisation $location,
                ): void {
                    $service->changeLocalisation($location);
                },
            ])
            ->add(
                "paysMobiles",
                StringListType::class,
                $this->field(
                    "Pays",
                    "paysMobiles",
                    "changePaysMobiles",
                ) + ["help" => "Un pays par ligne."],
            )
            ->add(
                "regionsMobiles",
                StringListType::class,
                $this->field(
                    "Région(s)",
                    "regionsMobiles",
                    "changeRegionsMobiles",
                ) + ["help" => "Une région par ligne."],
            )
            ->add(
                "departementsMobiles",
                StringListType::class,
                $this->field(
                    "Département(s)",
                    "departementsMobiles",
                    "changeDepartementsMobiles",
                ) + ["help" => "Un département par ligne."],
            );

        foreach (
            [
                "participantsMin" => ["Prestation à partir de combien de personnes ?", "changeParticipantsMin"],
                "participantsMax" => ["Prestation jusqu'à combien de personnes ?", "changeParticipantsMax"],
                "dureeMinutes" => ["Durée de la prestation (minutes)", "changeDureeMinutes"],
            ] as $name => [$label, $setter]
        ) {
            $builder->add(
                $name,
                IntegerType::class,
                $this->field($label, $name, $setter),
            );
        }

        foreach ($this->tariffFields() as $name => [$label, $setter]) {
            $builder->add(
                $name,
                MoneyType::class,
                $this->field($label, $name, $setter) + [
                    "currency" => "EUR",
                    "input" => "float",
                    "scale" => 2,
                ],
            );
        }

        $builder
            ->add(
                "youtubeUrl",
                UrlType::class,
                $this->field("Lien vidéo", "youtubeUrl", "changeYoutubeUrl") + [
                    "constraints" => [new LienVideo()],
                ],
            )
            ->add("ressources", CollectionType::class, [
                "entry_type" => ServiceRessourceType::class,
                "allow_add" => true,
                "allow_delete" => true,
                "by_reference" => false,
                "prototype" => true,
                "getter" => static fn(
                    ServiceEvenementiel $service,
                ): Collection => new ArrayCollection(
                    array_values(
                        array_filter(
                            $service->ressources()->toArray(),
                            static fn(
                                RessourceLieu $resource,
                            ): bool => NatureRessource::Photo ===
                                $resource->nature(),
                        ),
                    ),
                ),
                "setter" => static function (
                    ServiceEvenementiel &$service,
                    iterable $submitted,
                ): void {
                    $submitted = is_array($submitted)
                        ? $submitted
                        : iterator_to_array($submitted);
                    foreach ($service->ressources()->toArray() as $old) {
                        if (
                            NatureRessource::Photo === $old->nature() &&
                            !in_array($old, $submitted, true)
                        ) {
                            $service->removeRessource($old);
                        }
                    }
                    foreach ($submitted as $resource) {
                        if ($resource instanceof RessourceLieu) {
                            $service->addRessource($resource);
                        }
                    }
                },
            ])
            ->add("supportsCommerciaux", FileType::class, [
                "label" => "Supports commerciaux",
                "help" => "PDF, JPEG ou PNG.",
                "mapped" => false,
                "multiple" => true,
                "required" => false,
                "attr" => ["data-dropzone" => true, "accept" => ".pdf,.jpg,.jpeg,.png", "data-max-files" => 10, "data-max-size" => "100M"],
                "constraints" => [
                    new All([
                        new File(
                            maxSize: "100M",
                            mimeTypes: [
                                "application/pdf",
                                "image/jpeg",
                                "image/png",
                            ],
                        ),
                    ]),
                ],
            ])
            ->add("supportTitle", TextType::class, [
                "help" => "Appliqué aux fichiers déposés ci-dessus, à l’enregistrement.",
                "label" => "Titre des nouveaux supports",
                "mapped" => false,
                "required" => false,
            ])
            ->add("supportSource", TextType::class, [
                "label" => "Source des nouveaux supports",
                "mapped" => false,
                "required" => false,
            ]);

        // Facturation & partenariat (maquette portail) : bloc commun porté par la fiche.
        $builder->add('administratif', MethodMappedFieldsType::class, [
            'mapped_class' => FicheAdministratif::class,
            'data_class' => FicheAdministratif::class,
            'fields' => FicheFormCatalog::administrative(),
            'getter' => static fn (ServiceEvenementiel $entite): FicheAdministratif => $entite->administratif(),
            'setter' => static function (ServiceEvenementiel &$entite, FicheAdministratif $value): void {},
        ]);
        FicheFormCatalog::ajouterFichiers($builder);
        $builder->add('submit', SubmitType::class, ['label' => 'Enregistrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "data_class" => ServiceEvenementiel::class,
            "validation_groups" => [ValidationGroups::DRAFT],
        ]);
    }

    /**
     * Astérisque permanent des champs bloquants à la soumission (marqueur
     * `obligatoire` lu par le thème de l'éditeur, jamais l'option `required`).
     *
     * @param FormView             $view
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        ServiceEvenementielObligationsPublication::marquer($view);
    }

    /** @return array<string, array{string, string}> Libellés maquette portail. */
    private function booleanFields(): array
    {
        return [
            "prestataireEsat" => ["Êtes-vous un prestataire ESAT ?", "changePrestataireEsat"],
            "demarcheRse" => ["Êtes-vous un prestataire RSE ?", "changeDemarcheRse"],
            "adapteFemmesEnceintes" => [
                "Votre prestation est-elle adaptée aux femmes enceintes ?",
                "changeAdapteFemmesEnceintes",
            ],
            "adapteMalentendants" => [
                "Votre prestation est-elle adaptée aux personnes malentendantes ?",
                "changeAdapteMalentendants",
            ],
            "adapteMalvoyants" => [
                "Votre prestation est-elle adaptée aux personnes malvoyantes ?",
                "changeAdapteMalvoyants",
            ],
            "materielInclus" => ["Y a-t-il du matériel fourni ?", "changeMaterielInclus"],
            "contraintesLogistiques" => [
                "Y a-t-il des contraintes logistiques ?",
                "changeContraintesLogistiques",
            ],
            "equipementParticipantsRequis" => [
                "Y a-t-il des équipements à prévoir par les participants ?",
                "changeEquipementParticipantsRequis",
            ],
            "equipementReceptionRequis" => [
                "Y a-t-il des équipements à prévoir sur l'espace de réception ?",
                "changeEquipementReceptionRequis",
            ],
            "accesPmr" => ["Accès PMR", "changeAccesPmr"],
            "materielAdaptePmr" => ["Matériel ou prestation adaptée aux publics PMR", "changeMaterielAdaptePmr"],
            "surDevis" => ["Sur devis", "changeSurDevis"],
        ];
    }

    /** @return array<string, array{string, string}> Libellés maquette portail. */
    private function tariffFields(): array
    {
        return [
            "tarifParPrestation" => ["Par prestation", "changeTarifParPrestation"],
            "tarifParPersonne" => ["Par personne", "changeTarifParPersonne"],
            "tarifParJour" => ["Par jour", "changeTarifParJour"],
            "tarifParDemiJournee" => ["Par demi journée", "changeTarifParDemiJournee"],
            "tarifParHeure" => ["Par heure", "changeTarifParHeure"],
        ];
    }

    /** @return array<string, mixed> */
    private function field(string $label, string $getter, string $setter): array
    {
        return [
            "label" => $label,
            "required" => false,
            "getter" => static fn(
                ServiceEvenementiel $service,
            ): mixed => $service->{$getter}(),
            "setter" => static function (
                ServiceEvenementiel &$service,
                mixed $value,
            ) use ($setter): void {
                $service->{$setter}($value);
            },
        ];
    }
}
