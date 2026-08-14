<?php

declare(strict_types=1);

namespace App\Pim\Twig;

use App\Account\Entity\User;
use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use App\Pim\Model\ProviderPortal\DTO\UserDTO;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose le menu et l'utilisateur de la barre supérieure aux gabarits.
 *
 * La coquille en a besoin sur tous les écrans ; les faire passer par chaque
 * contrôleur reviendrait à répéter la même ligne partout. Les composants
 * `Header:Menu` et `Header:Profil` du portail restent intacts : ils reçoivent
 * ces valeurs par leurs propriétés `items` et `user`.
 *
 * Le menu et le menu d'administration reprennent la navigation réelle du
 * back-office (mêmes routes, mêmes gardes de rôle que l'ancien shell) : rien
 * n'est perdu. Une entrée sans route reste visible mais inerte tant que sa page
 * n'existe pas.
 */
final class EnteteExtension extends AbstractExtension
{
    public function __construct(private readonly Security $security)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('entete_menu', $this->menu(...)),
            new TwigFunction('entete_utilisateur', $this->utilisateur(...)),
            new TwigFunction('entete_administration', $this->administration(...)),
        ];
    }

    /**
     * Le menu du back-office, passé à `Header:Menu` et `BurgerMenu`.
     *
     * @return list<MenuDTOItem>
     */
    public function menu(): array
    {
        $entrees = [
            new MenuDTOItem('accueil', 'Accueil', 'app_mdm_tableau_de_bord'),
        ];

        if ($this->security->isGranted('ROLE_BP_EDITOR')) {
            $referentiel = (new MenuDTOItem('referentiel', 'Référentiel'))
                ->addItem($this->famille('referentiel.toutes', 'Toutes les fiches', 'squares-four', 'app_mdm_referentiel_general'))
                ->addItem($this->famille('referentiel.lieux', 'Lieux', 'building', 'app_mdm_lieux'))
                ->addItem($this->famille('referentiel.restaurants', 'Restaurants', 'utensils', 'app_mdm_referentiel_gamme', ['gamme' => 'restaurants']))
                ->addItem($this->famille('referentiel.activites', 'Activités', 'biking', 'app_mdm_referentiel_gamme', ['gamme' => 'activites']))
                ->addItem($this->famille('referentiel.services', 'Services', 'call-bell', 'app_mdm_referentiel_gamme', ['gamme' => 'services']));
            // Plateaux repas : pas encore de route dédiée. Le composant NavLink
            // exige une route ; l'entrée réapparaîtra quand la gamme sera branchée
            // (comme l'ancienne navigation, qui n'affichait pas les écrans sans page).

            $entrees[] = $referentiel;
            $entrees[] = new MenuDTOItem('qualite', 'Qualité', 'app_mdm_qualite');
            $entrees[] = new MenuDTOItem('medias', 'Médias', 'app_mdm_medias');
        }

        if ($this->security->isGranted('ROLE_BP_VALIDATOR')) {
            $entrees[] = new MenuDTOItem('outils', 'Outils', 'app_mdm_outils');
        }

        return $entrees;
    }

    /**
     * L'utilisateur identifié, adapté depuis l'entité de sécurité.
     *
     * L'entité `User` ne porte pas de nom/prénom : on affiche l'e-mail et on en
     * dérive un libellé lisible tant qu'un vrai profil n'est pas disponible.
     */
    public function utilisateur(): UserDTO
    {
        $dto = new UserDTO();
        $user = $this->security->getUser();

        if ($user instanceof User) {
            $identifiant = $user->getUserIdentifier();
            $dto->email = $identifiant;
            $dto->firstName = strtok($identifiant, '@') ?: $identifiant;
            $dto->lastName = '';
            $dto->job = $this->fonction();
        }

        return $dto;
    }

    /**
     * Le menu d'administration, ouvert au survol du profil.
     *
     * Reprend l'intégralité des écrans d'administration de l'ancienne
     * navigation, avec les mêmes gardes de rôle. Libellé, glyphe, route.
     *
     * @return list<array{string, string, string}>
     */
    public function administration(): array
    {
        $entrees = [];

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $entrees = [
                ['Sommaire', 'squares-four', 'app_pim_admin'],
                ['Statistiques détaillées', 'trend-up', 'app_dashboard_index'],
                ['Traitements en échec', 'warning', 'app_dashboard_traitements_en_echec'],
                ['Relances de complétude', 'paper-plane', 'app_pim_relance_completude_index'],
                ['Listes de valeurs', 'list', 'app_pim_lov_index'],
                ['Sites de diffusion', 'rocket', 'app_pim_site_diffusion_index'],
                ['Médiathèque (DAM)', 'image', 'app_dam_dashboard'],
                ['Import de fiches', 'upload-simple', 'app_etl_import_index'],
                ['Paramètres', 'gear', 'app_parametre_index'],
            ];
        }

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            $entrees[] = ['Complétude', 'star', 'app_pim_completeness_index'];
            $entrees[] = ['Collaborateurs', 'users', 'app_account_admin_index'];
            $entrees[] = ['Utilisateurs', 'user', 'app_account_user_admin_index'];
        }

        return $entrees;
    }

    private function fonction(): ?string
    {
        return match (true) {
            $this->security->isGranted('ROLE_SUPER_ADMIN') => 'Super administrateur',
            $this->security->isGranted('ROLE_ADMIN') => 'Administrateur',
            $this->security->isGranted('ROLE_BP_VALIDATOR') => 'Validateur',
            $this->security->isGranted('ROLE_BP_EDITOR') => 'Éditeur',
            default => null,
        };
    }

    /**
     * @param array<string, string> $parametres
     */
    private function famille(string $code, string $libelle, string $glyphe, ?string $route, array $parametres = []): MenuDTOItem
    {
        $item = (new MenuDTOItem($code, $libelle, $route))
            ->setIcon($glyphe)
            ->setIconColor(TypographyTextColorEnum::PRIMARY);

        if ([] !== $parametres) {
            $item->setRouteParameters($parametres);
        }

        return $item;
    }
}
