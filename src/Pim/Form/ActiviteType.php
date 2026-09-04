<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Etl\Repository\FicheSalesforceRepository;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\FicheAdministratif;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\ModeInterventionActivite;
use App\Pim\Lov\ActiviteLovCatalog;
use App\Pim\Service\ActiviteObligationsPublication;
use App\Pim\Validation\LienVideo;
use App\Pim\Validation\ValidationGroups;
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

/** @extends AbstractType<Activite> */
final class ActiviteType extends AbstractType
{
    public function __construct(private readonly FicheSalesforceRepository $salesforce)
    {
    }

    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        // Fiche connue de Salesforce : le statut partenaire est écrasé à
        // chaque refresh, la case devient consultative.
        $data = $options['data'] ?? null;
        $partenaireGereParSf = $data instanceof Activite
            && $this->salesforce->existePourFiche($data->fiche()->id());
        $b->add(
            'label',
            TextType::class,
            $this->field('Nom de l’activité', 'label', 'changeLabel'),
        )
            ->add('businessPremium', CheckboxType::class, [
                'label' => 'Adhérent Business Premium',
                'required' => false,
                'getter' => static fn (Activite $activite): bool => $activite->fiche()->businessPremium(),
                'setter' => static function (Activite &$activite, mixed $value): void { $activite->fiche()->changeBusinessPremium((bool) $value); },
            ])
            ->add('partenaireBp', CheckboxType::class, [
                'label' => 'Partenaire BP',
                'required' => false,
                'disabled' => $partenaireGereParSf,
                'help' => $partenaireGereParSf ? 'Géré par Salesforce.' : null,
                // Chrome « autorité » : seul champ réellement piloté par Salesforce (défaut MDM ailleurs).
                'label_attr' => $partenaireGereParSf ? ['data-autorite' => \App\Pim\Enum\Autorite::Salesforce->value] : [],
                'getter' => static fn (Activite $activite): bool => $activite->fiche()->partenaireBp(),
                'setter' => static function (Activite &$activite, mixed $value): void { $activite->fiche()->changePartenaireBp((bool) $value); },
            ])
            ->add(
                'prestataire',
                PrestataireAutocompleteType::class,
                $this->field('Prestataire', 'prestataire', 'changePrestataire'),
            )
            ->add(
                'types',
                ChoiceType::class,
                $this->field(
                    'Type',
                    'types',
                    'changeTypes',
                ) + [
                    'choices' => array_flip(
                        ActiviteLovCatalog::choicesFor('TYPE_EXT_INT'),
                    ),
                    'multiple' => true,
                    'expanded' => false,
                ],
            )
            ->add(
                'thematiques',
                ChoiceType::class,
                $this->field(
                    "Thématique de l'activité",
                    'thematiques',
                    'changeThematiques',
                ) + [
                    'choices' => array_flip(
                        ActiviteLovCatalog::choicesFor('THEMATIQUE_ACTIVITE'),
                    ),
                    'multiple' => true,
                    // Select multiple (composant Select) — plus de cases filtrées.
                    'expanded' => false,
                ],
            );

        // Un select de sous-thématiques par thématique, libellé = la
        // thématique ; l'éditeur ne montre que ceux des thématiques cochées
        // (affichage-conditionnel, FicheSectionsCatalogue::activite).
        foreach (ActiviteLovCatalog::sousThematiqueAttributes() as $attribute => $thematique) {
            $b->add(
                ActiviteLovCatalog::SOUS_THEMATIQUE_FIELDS[$attribute],
                ChoiceType::class,
                [
                    'label' => $thematique,
                    'required' => false,
                    'getter' => static fn (
                        Activite $activite,
                    ): array => $activite->sousThematiquesPour($attribute),
                    'setter' => static function (
                        Activite &$activite,
                        mixed $value,
                    ) use ($attribute): void {
                        $activite->changeSousThematiquesPour(
                            $attribute,
                            array_values(array_filter((array) $value, is_string(...))),
                        );
                    },
                    'choices' => array_flip(
                        ActiviteLovCatalog::choicesFor($attribute),
                    ),
                    'multiple' => true,
                    'expanded' => false,
                ],
            );
        }

        $b
            ->add(
                'langues',
                ChoiceType::class,
                $this->field('Langue parlée', 'langues', 'changeLangues') + [
                    'choices' => array_flip(
                        ActiviteLovCatalog::choicesFor('LANGUE_PARLEE'),
                    ),
                    'multiple' => true,
                ],
            )
            ->add(
                'engagementsRse',
                ChoiceType::class,
                $this->field(
                    'Engagements RSE',
                    'engagementsRse',
                    'changeEngagementsRse',
                ) + [
                    'choices' => array_flip(
                        ActiviteLovCatalog::choicesFor('ENGAGEMENT_RSE'),
                    ),
                    'multiple' => true,
                ],
            )
            ->add(
                'modeIntervention',
                ChoiceType::class,
                $this->field(
                    'Rayon d’action',
                    'modeIntervention',
                    'changeModeIntervention',
                ) + [
                    'choices' => [
                        'Localisation fixe' => ModeInterventionActivite::Fixe,
                        'Localisation mobile' => ModeInterventionActivite::Mobile,
                    ],
                    'choice_value' => static fn (
                        ?ModeInterventionActivite $mode,
                    ): ?string => $mode?->value,
                    // Radios maquette (bloc data-radio du thème) : pilotent
                    // les cartes Localisation fixe / mobile.
                    'expanded' => true,
                    'placeholder' => false,
                    'attr' => ['data-radio' => true],
                ],
            )
            ->add('localisation', LocalisationType::class, [
                'label' => 'Localisation fixe',
                'required' => false,
                'empty_data' => static fn (): Localisation => new Localisation(),
                'getter' => static fn (
                    Activite $a,
                ): ?Localisation => $a->localisation(),
                'setter' => static function (
                    Activite &$a,
                    ?Localisation $v,
                ): void {
                    $a->changeLocalisation($v);
                },
            ])
            ->add(
                'touteFrance',
                CheckboxType::class,
                $this->field(
                    'Toute la France',
                    'touteFrance',
                    'changeTouteFrance',
                ),
            )
            ->add(
                'paysMobiles',
                StringListType::class,
                $this->field(
                    'Pays',
                    'paysMobiles',
                    'changePaysMobiles',
                ) + ['help' => 'Un pays par ligne.'],
            )
            ->add(
                'regionsMobiles',
                StringListType::class,
                $this->field(
                    'Région(s)',
                    'regionsMobiles',
                    'changeRegionsMobiles',
                ) + ['help' => 'Une région par ligne.'],
            )
            ->add(
                'departementsMobiles',
                StringListType::class,
                $this->field(
                    'Département(s)',
                    'departementsMobiles',
                    'changeDepartementsMobiles',
                ) + ['help' => 'Un département par ligne.'],
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
                'comprendPrestation',
                TextareaType::class,
                $this->field(
                    'Ce que comprend la prestation',
                    'comprendPrestation',
                    'changeComprendPrestation',
                ),
            )
            ->add(
                'objectifs',
                ChoiceType::class,
                $this->field(
                    'Objectifs de séminaire',
                    'objectifs',
                    'changeObjectifs',
                ) + [
                    'choices' => array_flip(
                        ActiviteLovCatalog::choicesFor('OBJECTIF_SEMINAIRE'),
                    ),
                    'multiple' => true,
                    'help' => 'Cinq objectifs au maximum.',
                    'attr' => ['data-max' => 5],
                ],
            )
            ->add(
                'participantsMin',
                IntegerType::class,
                $this->field(
                    'Nombre de participants minimum',
                    'participantsMin',
                    'changeParticipantsMin',
                ),
            )
            ->add(
                'participantsMax',
                IntegerType::class,
                $this->field(
                    'Nombre de participants maximum',
                    'participantsMax',
                    'changeParticipantsMax',
                ),
            )
            ->add(
                'dureeMinMinutes',
                DureeMinutesType::class,
                $this->field(
                    'Temps minimum',
                    'dureeMinMinutes',
                    'changeDureeMinMinutes',
                ),
            )
            ->add(
                'dureeMaxMinutes',
                DureeMinutesType::class,
                $this->field(
                    'Temps maximum',
                    'dureeMaxMinutes',
                    'changeDureeMaxMinutes',
                ),
            )
            ->add(
                'plus',
                ListeIndexeeType::class,
                $this->field(
                    'Les plus',
                    'plus',
                    'changePlus',
                ) + ['nombre' => Activite::PLUS_MAX, 'libelle_format' => 'Plus n°%d', 'entry_attr' => ['maxlength' => 255]],
            )
            ->add(
                'tarifParPersonne',
                MoneyType::class,
                $this->field(
                    'Tarifs à partir de (/pers)',
                    'tarifParPersonne',
                    'changeTarifParPersonne',
                ) + ['currency' => 'EUR', 'input' => 'string'],
            )
            ->add(
                'youtubeUrl',
                UrlType::class,
                $this->field('Lien vidéo', 'youtubeUrl', 'changeYoutubeUrl')
                    + ['constraints' => [new LienVideo()]],
            )
            ->add('ressources', CollectionType::class, [
                'entry_type' => ActiviteRessourceType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'getter' => static fn (
                    Activite $a,
                ): Collection => new \Doctrine\Common\Collections\ArrayCollection(
                    array_values(
                        array_filter(
                            $a->ressources()->toArray(),
                            static fn (
                                RessourceLieu $r,
                            ): bool => \App\Pim\Enum\NatureRessource::Photo ===
                                $r->nature(),
                        ),
                    ),
                ),
                'setter' => static function (
                    Activite &$a,
                    iterable $submitted,
                ): void {
                    $submitted = is_array($submitted)
                        ? $submitted
                        : iterator_to_array($submitted);
                    foreach ($a->ressources()->toArray() as $old) {
                        if (
                            \App\Pim\Enum\NatureRessource::Photo ===
                                $old->nature()
                            && !in_array($old, $submitted, true)
                        ) {
                            $a->removeRessource($old);
                        }
                    }
                    foreach ($submitted as $resource) {
                        if ($resource instanceof RessourceLieu) {
                            $a->addRessource($resource);
                        }
                    }
                },
            ])
            ->add('supportsCommerciaux', FileType::class, [
                'label' => 'Supports commerciaux',
                'help' => 'PDF, JPEG ou PNG.',
                'mapped' => false,
                'multiple' => true,
                'required' => false,
                'attr' => ['data-dropzone' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'data-max-files' => 10, 'data-max-size' => '100M'],
                'constraints' => [
                    new All([
                        new File(
                            maxSize: '100M',
                            mimeTypes: [
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                            ],
                        ),
                    ]),
                ],
            ])
            ->add('supportTitle', TextType::class, [
                'help' => 'Appliqué aux fichiers déposés ci-dessus, à l’enregistrement.',
                'label' => 'Titre des nouveaux supports',
                'mapped' => false,
                'required' => false,
            ])
            ->add('supportSource', TextType::class, [
                'label' => 'Source des nouveaux supports',
                'mapped' => false,
                'required' => false,
            ])
            ->add('offres', CollectionType::class, [
                'label' => 'Offres',
                'entry_type' => OffreActiviteType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'getter' => static fn (Activite $a): Collection => $a->offres(),
                'setter' => static function (
                    Activite &$a,
                    iterable $submitted,
                ): void {
                    $submitted = is_array($submitted)
                        ? $submitted
                        : iterator_to_array($submitted);
                    foreach ($a->offres()->toArray() as $old) {
                        if (!in_array($old, $submitted, true)) {
                            $a->removeOffre($old);
                        }
                    }
                    foreach ($submitted as $offer) {
                        $a->addOffre($offer);
                    }
                },
            ]);

        // Facturation & partenariat (maquette portail) : bloc commun porté par la fiche.
        $b->add('administratif', MethodMappedFieldsType::class, [
            'mapped_class' => FicheAdministratif::class,
            'data_class' => FicheAdministratif::class,
            'fields' => FicheFormCatalog::administrative(),
            'getter' => static fn (Activite $entite): FicheAdministratif => $entite->administratif(),
            'setter' => static function (Activite &$entite, FicheAdministratif $value): void {},
        ]);
        FicheFormCatalog::ajouterFichiers($b);
        $b->add('submit', SubmitType::class, ['label' => 'Enregistrer']);
    }

    /** @return array<string,mixed> */
    private function field(string $label, string $getter, string $setter): array
    {
        return [
            'label' => $label,
            'required' => false,
            'getter' => static fn (Activite $a): mixed => $a->{$getter}(),
            'setter' => static function (Activite &$a, mixed $v) use (
                $setter,
            ): void {
                $a->{$setter}($v);
            },
        ];
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => Activite::class,
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
        ActiviteObligationsPublication::marquer($view);
    }
}
