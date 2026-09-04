<?php

declare(strict_types=1);

namespace App\Pim\Service\Editeur;

use App\Pim\Completeness\CompletenessCalculator;
use App\Pim\Completeness\CompletenessFieldCatalog;
use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\CompletenessFieldConfigurationRepository;
use App\Pim\Service\FicheRouteResolver;
use App\Pim\Service\FicheSectionsCatalogue;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;

/**
 * Rail des sections de l'éditeur : onglets avec complétude, icône, groupe et
 * URL, compteur de champs de chaque section, et les URL de section utilisées
 * par les contrôleurs qui redirigent vers un bloc précis.
 */
final readonly class EditeurNavigation
{
    public function __construct(
        private CompletenessFieldConfigurationRepository $configurations,
        private CompletenessFieldCatalog $catalog,
        private CompletenessCalculator $calculator,
        private FicheRouteResolver $routes,
    ) {
    }

    /**
     * @param FormInterface<mixed> $form le formulaire complet de la gamme (compteur de champs)
     *
     * @return array{onglets: list<array<string, mixed>>, sections: list<array<string, mixed>>, section: array<string, mixed>, section_index: int}
     */
    public function variables(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, int $index, FormInterface $form): array
    {
        $fiche = $entite->fiche();
        $type = $fiche->type();
        $sections = FicheSectionsCatalogue::pour($type);
        // Compteur de champs des en-têtes de carte (maquette) : une section
        // range souvent un unique sous-formulaire, on compte ses feuilles.
        foreach ($sections as $i => $section) {
            $nb = 0;
            foreach ($section['champs'] as $champ) {
                // Un champ de section peut être une feuille pointée (`groupe.champ`).
                $noeud = $form;
                foreach (explode('.', $champ) as $segment) {
                    if (!$noeud->has($segment)) {
                        continue 2;
                    }
                    $noeud = $noeud->get($segment);
                }
                $nb += self::nbChampsTerminaux($noeud);
            }
            $sections[$i]['nb_champs'] = $nb;
        }
        $parSection = $this->completudesParSection($entite, $sections);
        $onglets = [];
        foreach ($sections as $i => $section) {
            $onglets[] = [
                'index' => $i,
                'titre' => $section['titre'],
                'completude' => $parSection[$i],
                'actif' => $i === $index,
                'url' => $this->routes->editUrl($type, $fiche->idString(), $i),
                'icone' => self::iconeSection($section['titre']),
                'groupe' => $section['groupe'] ?? self::groupeSection($section['blocs']),
            ];
        }

        return ['onglets' => $onglets, 'sections' => $sections, 'section' => $sections[$index], 'section_index' => $index];
    }

    /** URL d'une section précise de l'éditeur, toutes gammes. */
    public function urlSection(TypeFiche $type, string $id, int $index): string
    {
        return $this->routes->editUrl($type, $id, $index);
    }

    /** URL de la section de l'éditeur qui porte le bloc extraction. */
    public function urlExtraction(TypeFiche $type, string $id): string
    {
        $index = 0;
        foreach (FicheSectionsCatalogue::pour($type) as $i => $section) {
            if (in_array('suggestions', $section['blocs'], true)) {
                $index = $i;
                break;
            }
        }

        return $this->routes->editUrl($type, $id, $index);
    }

    /**
     * Icône du rail pour une section, dérivée de son titre (glyphes du
     * design-system). Repli neutre pour les sections non mappées.
     */
    private static function iconeSection(string $titre): string
    {
        $t = mb_strtolower($titre);

        return match (true) {
            str_contains($t, 'information') => 'info-circle',
            // Avant localisation/accès : « Prestation & accessibilité » contient « acces ».
            str_contains($t, 'prestation') => 'call-bell',
            str_contains($t, 'classification') => 'squares-four',
            str_contains($t, 'localisation'), str_contains($t, 'accès'), str_contains($t, 'acces') => 'area',
            str_contains($t, 'descript') => 'note',
            str_contains($t, 'héberg'), str_contains($t, 'heberg') => 'bed',
            str_contains($t, 'restaur') => 'utensils',
            str_contains($t, 'salle'), str_contains($t, 'capacit'), str_contains($t, 'réunion'), str_contains($t, 'reunion') => 'conference',
            str_contains($t, 'thématique'), str_contains($t, 'thematique'), str_contains($t, 'ambiance') => 'confetti',
            str_contains($t, 'facturation'), str_contains($t, 'partenariat') => 'list',
            str_contains($t, 'template'), str_contains($t, 'message') => 'paper-plane',
            str_contains($t, 'service'), str_contains($t, 'équipement'), str_contains($t, 'equipement') => 'gear',
            str_contains($t, 'rse'), str_contains($t, 'engagement') => 'plant',
            str_contains($t, 'loisir'), str_contains($t, 'bien-être'), str_contains($t, 'bien-etre') => 'spa',
            str_contains($t, 'tarif'), str_contains($t, 'formule') => 'currency-euro',
            str_contains($t, 'administratif') => 'list',
            str_contains($t, 'média'), str_contains($t, 'media') => 'images',
            str_contains($t, 'disponibil'), str_contains($t, 'fermeture') => 'calendar',
            str_contains($t, 'collaborateur') => 'users',
            str_contains($t, 'visibilité'), str_contains($t, 'visibilite'), str_contains($t, 'diffusion') => 'rocket',
            str_contains($t, 'suggestion'), str_contains($t, 'historique'), str_contains($t, 'ia') => 'star',
            default => 'note',
        };
    }

    /**
     * Groupe de rail d'une section : « Paramètres » pour les sections de
     * configuration (blocs médias/collaborateurs/diffusion/salesforce/
     * historique), « Ma fiche » pour le contenu éditorial.
     *
     * @param list<string> $blocs
     */
    private static function groupeSection(array $blocs): string
    {
        $configuration = ['medias', 'collaborateurs', 'sites', 'salesforce', 'historique'];

        return array_intersect($blocs, $configuration) !== [] ? 'parametres' : 'ma_fiche';
    }

    /**
     * Nombre de champs « terminaux » d'un champ de formulaire : une liste de
     * choix ou une collection comptent pour un, un sous-formulaire pour la
     * somme de ses feuilles.
     *
     * @param FormInterface<mixed> $champ
     */
    private static function nbChampsTerminaux(FormInterface $champ): int
    {
        $type = $champ->getConfig()->getType()->getInnerType();
        if ($type instanceof ChoiceType || null !== $champ->getConfig()->getOption('prototype') || 0 === $champ->count()) {
            return 1;
        }
        $nb = 0;
        foreach ($champ as $enfant) {
            $nb += self::nbChampsTerminaux($enfant);
        }

        return $nb;
    }

    /**
     * @param list<array<string, mixed>> $sections
     *
     * @return array<int, ?int> Complétude par section (null quand la section ne porte aucun champ pondéré)
     */
    private function completudesParSection(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, array $sections): array
    {
        $type = $entite->fiche()->type();
        $parPropriete = [];
        foreach ($this->configurations->activeFor($type) as $configuration) {
            $definition = $this->catalog->find($type, $configuration->fieldCode());
            if (null === $definition) {
                continue;
            }
            $racine = explode('.', $definition->path, 2)[0];
            $parPropriete[$racine][] = $configuration;
        }
        $resultats = [];
        foreach ($sections as $i => $section) {
            $subset = [];
            foreach ($section['proprietes'] as $propriete) {
                $subset = [...$subset, ...($parPropriete[$propriete] ?? [])];
            }
            $resultats[$i] = [] === $subset ? null : $this->calculator->calculate($entite, $type, $subset)->global;
        }

        return $resultats;
    }
}
