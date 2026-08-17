<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\SiteDiffusion;
use App\Pim\Enum\TypeFiche;
use App\Pim\Form\LocalisationType;
use App\Pim\Lov\ActiviteLovCatalog;
use App\Pim\Lov\ServiceLovCatalog;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\SiteDiffusionRepository;
use App\Pim\Repository\ValeurAttributRepository;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Formulaire et enregistrement de l'édition rapide : adresse, classification
 * par gamme et sites de diffusion. Les sites obligatoires sont réappliqués
 * côté serveur — une case désactivée ne se soumet pas, elle ne doit pas
 * décocher pour autant.
 */
final readonly class EditionRapideEcran
{
    /** Attributs de classification proposés par gamme, alignés sur la maquette.
     * @return list<string>
     */
    private static function classification(string $type): array
    {
        return match ($type) {
            'lieu' => ['GENERALE_TYPOLOGIE', 'TA_THEMATIQUE'],
            'restaurant' => ['TYPE_RESTAURANT', 'TYPE_CUISINE'],
            'activite' => ['THEMATIQUE_ACTIVITE', ...array_keys(ActiviteLovCatalog::sousThematiqueAttributes())],
            'service_evenementiel' => ['TYPE_PRESTATAIRE', ...array_keys(ServiceLovCatalog::sousPrestationAttributes())],
            default => [],
        };
    }

    public function __construct(
        private FicheRepository $fiches,
        private SiteDiffusionRepository $sites,
        private ValeurAttributRepository $valeurs,
        private FormFactoryInterface $forms,
        private InternalFicheMutationPolicy $policy,
        private FicheWorkflowManager $workflow,
    ) {
    }

    /** @return FormInterface<mixed> */
    public function form(Fiche $fiche, Localisation $localisation): FormInterface
    {
        $builder = $this->forms->createBuilder(options: ['csrf_token_id' => 'edition-rapide-'.$fiche->idString()])
            ->add('localisation', LocalisationType::class, [
                'label' => 'Adresse',
                'data' => $localisation,
                'mapped' => false,
            ])
            ->add('businessPremium', CheckboxType::class, [
                'label' => 'Adhérent Business Premium',
                'required' => false,
                'data' => $fiche->businessPremium(),
            ]);
        foreach ($this->attributsClassification($fiche->type()) as $attribut) {
            $builder->add('classification_'.$attribut['code'], ChoiceType::class, [
                'label' => $attribut['label'],
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => $attribut['choices'],
                'data' => $fiche->valueIdsFor($attribut['code']),
            ]);
        }
        $obligatoires = [];
        $choixSites = [];
        foreach ($this->sites->findActifsOrdonnes() as $site) {
            $mention = $site->obligatoire() ? ' (obligatoire)' : ($site->payant() ? ' (payant)' : '');
            $choixSites[$site->groupe()][$site->label().$mention] = $site->id();
            if ($site->obligatoire()) {
                $obligatoires[] = $site->id();
            }
        }
        $selection = array_values(array_unique([...$fiche->siteDiffusionIds(), ...$obligatoires]));
        $builder->add('sites', ChoiceType::class, [
            'label' => 'Sites de diffusion',
            'required' => false,
            'multiple' => true,
            'expanded' => true,
            'choices' => $choixSites,
            'data' => $selection,
            'choice_attr' => static fn (int $id): array => in_array($id, $obligatoires, true)
                ? ['disabled' => 'disabled']
                : [],
        ]);
        $builder->add('enregistrer', SubmitType::class, ['label' => 'Enregistrer']);
        // Second bouton de la maquette : enregistrer puis passer à la
        // fiche suivante du filtre courant.
        $builder->add('enregistrerSuivante', SubmitType::class, ['label' => 'Enregistrer et passer à la suivante']);

        return $builder->getForm();
    }

    /** @param array<string, mixed> $data Données soumises du formulaire. */
    public function enregistrer(Fiche $fiche, Localisation $localisation, array $data): void
    {
        $this->policy->execute($fiche, function () use ($fiche, $localisation, $data): void {
            if (null === $fiche->localisation()) {
                $fiche->changeLocalisation($localisation);
            }
            $fiche->changeBusinessPremium((bool) ($data['businessPremium'] ?? false));
            foreach ($this->attributsClassification($fiche->type()) as $attribut) {
                /** @var list<int> $valeurs */
                $valeurs = $data['classification_'.$attribut['code']] ?? [];
                $fiche->replaceAttributeValues($attribut['code'], $valeurs);
            }
            /** @var list<int> $sitesChoisis */
            $sitesChoisis = $data['sites'] ?? [];
            $fiche->replaceSiteDiffusion($this->sitesRetenus($sitesChoisis));
        });
        $this->workflow->indexAndFlush($fiche);
    }

    /** Nombre d'autres fiches partageant la même adresse (empreinte normalisée). */
    public function adressesPartagees(Fiche $fiche): int
    {
        $empreinte = $fiche->localisation()?->addressFingerprint();
        if (null === $empreinte) {
            return 0;
        }

        return count(array_filter(
            $this->fiches->findIdsByAddressFingerprints([$empreinte]),
            static fn (string $id): bool => !Ulid::fromString($id)->equals($fiche->id()),
        ));
    }

    /** @return list<array{code: string, label: string, choices: array<string, int>}> */
    private function attributsClassification(TypeFiche $type): array
    {
        return $this->valeurs->classificationChoices(self::classification($type->value));
    }

    /**
     * @param list<int> $choisis
     *
     * @return list<SiteDiffusion>
     */
    private function sitesRetenus(array $choisis): array
    {
        return array_values(array_filter(
            $this->sites->findActifsOrdonnes(),
            static fn (SiteDiffusion $site): bool => $site->obligatoire() || in_array($site->id(), $choisis, true),
        ));
    }
}
