<?php

declare(strict_types=1);

namespace App\Pim\Form;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Localisation;
use App\Pim\Enum\ModeInterventionActivite;
use App\Pim\Lov\ActiviteLovCatalog;
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
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

/** @extends AbstractType<Activite> */
final class ActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b->add(
                'label',
                TextType::class,
                $this->field('Nom de l’activité', 'label', 'changeLabel'),
            )
            ->add(
                'prestataire',
                PrestataireAutocompleteType::class,
                $this->field('Prestataire', 'prestataire', 'changePrestataire'),
            )
            ->add(
                'types',
                ChoiceType::class,
                $this->field(
                    'Intérieur / extérieur',
                    'types',
                    'changeTypes',
                ) + [
                    'choices' => array_flip(
                        ActiviteLovCatalog::choicesFor('TYPE_EXT_INT'),
                    ),
                    'multiple' => true,
                    'expanded' => true,
                ],
            )
            ->add(
                'thematique',
                ChoiceType::class,
                $this->field('Thématique', 'thematique', 'changeThematique') + [
                    'choices' => array_flip(
                        ActiviteLovCatalog::choicesFor('THEMATIQUE_ACTIVITE'),
                    ),
                    'placeholder' => 'Choisir…',
                ],
            )
            ->add(
                'langues',
                ChoiceType::class,
                $this->field('Langues parlées', 'langues', 'changeLangues') + [
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
                    'placeholder' => 'Choisir…',
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
                    'Pays mobiles — un par ligne',
                    'paysMobiles',
                    'changePaysMobiles',
                ),
            )
            ->add(
                'regionsMobiles',
                StringListType::class,
                $this->field(
                    'Régions mobiles — une par ligne',
                    'regionsMobiles',
                    'changeRegionsMobiles',
                ),
            )
            ->add(
                'departementsMobiles',
                StringListType::class,
                $this->field(
                    'Départements mobiles — un par ligne',
                    'departementsMobiles',
                    'changeDepartementsMobiles',
                ),
            )
            ->add(
                'descriptionGenerale',
                TextareaType::class,
                $this->field(
                    'Description générale',
                    'descriptionGenerale',
                    'changeDescriptionGenerale',
                ),
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
                ],
            )
            ->add(
                'participantsMin',
                IntegerType::class,
                $this->field(
                    'Participants minimum',
                    'participantsMin',
                    'changeParticipantsMin',
                ),
            )
            ->add(
                'participantsMax',
                IntegerType::class,
                $this->field(
                    'Participants maximum',
                    'participantsMax',
                    'changeParticipantsMax',
                ),
            )
            ->add(
                'dureeMinMinutes',
                IntegerType::class,
                $this->field(
                    'Durée minimale en minutes',
                    'dureeMinMinutes',
                    'changeDureeMinMinutes',
                ),
            )
            ->add(
                'dureeMaxMinutes',
                IntegerType::class,
                $this->field(
                    'Durée maximale en minutes',
                    'dureeMaxMinutes',
                    'changeDureeMaxMinutes',
                ),
            )
            ->add(
                'plus',
                StringListType::class,
                $this->field(
                    'Les plus — quatre maximum, un par ligne',
                    'plus',
                    'changePlus',
                ),
            )
            ->add(
                'tarifParPersonne',
                MoneyType::class,
                $this->field(
                    'Tarif à partir de par personne',
                    'tarifParPersonne',
                    'changeTarifParPersonne',
                ) + ['currency' => 'EUR', 'input' => 'string'],
            )
            ->add(
                'youtubeUrl',
                UrlType::class,
                $this->field('Lien YouTube', 'youtubeUrl', 'changeYoutubeUrl'),
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
                'label' => 'Supports commerciaux PDF, JPEG ou PNG',
                'mapped' => false,
                'multiple' => true,
                'required' => false,
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
            ])
            ->add('submit', SubmitType::class, ['label' => 'Enregistrer']);
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
}
