<?php

declare(strict_types=1);

namespace App\Pim\Maquette;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTO;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use App\Pim\Model\ProviderPortal\DTO\UserDTO;

/**
 * Contenu de la barre supérieure : le menu et l'utilisateur identifié.
 *
 * Les composants `Header:Menu` et `Header:Profil` sont ceux du portail, repris
 * sans retouche. Ils prennent leur contenu par `items` et `user` — c'est par là
 * qu'on leur passe celui du back-office MDM plutôt que celui de nodevo, dont le
 * constructeur cite des routes qui n'existent pas ici.
 *
 * C'est du contenu de démonstration : il disparaît dès qu'un service métier
 * alimente le menu et que l'utilisateur authentifié porte son profil.
 */
final class EnteteMaquette
{
    public const ACCUEIL = 'accueil';
    public const REFERENTIEL = 'referentiel';
    public const FICHE = 'fiche';
    public const QUALITE = 'qualite';
    public const MEDIAS = 'medias';
    public const IMPORTS = 'imports';
    public const SYNCHRONISATION = 'synchronisation';
    public const ADMINISTRATION = 'administration';

    /**
     * Les six familles du référentiel, en menu déroulant.
     *
     * Même forme que « Fiches » dans le portail : un parent sans route, des
     * enfants avec glyphe. Les familles pas encore intégrées passent par
     * l'écran d'attente, faute de destination propre.
     *
     * Code, libellé, glyphe, route, paramètres.
     *
     * @var list<array{string, string, string, string, array<string, string>}>
     */
    private const FAMILLES = [
        ['referentiel.toutes', 'menu.header.referentiel.toutes', 'squares-four', 'app_mdm_referentiel_general', []],
        ['referentiel.lieux', 'menu.header.referentiel.lieux', 'building', 'app_mdm_lieux', []],
        ['referentiel.restaurants', 'menu.header.referentiel.restaurants', 'utensils', 'app_mdm_a_venir', ['ecran' => 'restaurants']],
        ['referentiel.activites', 'menu.header.referentiel.activites', 'biking', 'app_mdm_a_venir', ['ecran' => 'activites']],
        ['referentiel.services', 'menu.header.referentiel.services', 'call-bell', 'app_mdm_a_venir', ['ecran' => 'services']],
        ['referentiel.plateaux', 'menu.header.referentiel.plateaux', 'cookie', 'app_mdm_a_venir', ['ecran' => 'plateaux']],
    ];

    /**
     * Les entrées de premier niveau qui ne sont pas des menus déroulants.
     *
     * Code, libellé, route, paramètres.
     *
     * @var list<array{string, string, string, array<string, string>}>
     */
    private const ENTREES = [
        [self::FICHE, 'menu.header.fiche', 'app_mdm_fiche_lieu', []],
        [self::QUALITE, 'menu.header.qualite', 'app_mdm_a_venir', ['ecran' => 'qualite']],
        [self::MEDIAS, 'menu.header.medias', 'app_mdm_a_venir', ['ecran' => 'medias']],
        [self::IMPORTS, 'menu.header.imports', 'app_mdm_a_venir', ['ecran' => 'imports']],
        [self::SYNCHRONISATION, 'menu.header.synchronisation', 'app_mdm_a_venir', ['ecran' => 'synchronisation']],
        [self::ADMINISTRATION, 'menu.header.administration', 'app_mdm_a_venir', ['ecran' => 'administration']],
    ];

    /**
     * Pastilles de notification, par code d'entrée.
     *
     * Reprises des compteurs du tableau de bord pour que l'en-tête et l'écran
     * d'accueil ne puissent pas se contredire.
     *
     * @var array<string, int>
     */
    private const NOTIFICATIONS = [
        self::QUALITE => 318,
    ];

    /**
     * Le menu du back-office, à passer à `Header:Menu` et `BurgerMenu`.
     *
     * @return list<MenuDTOItem>
     */
    public static function menu(): array
    {
        $referentiel = new MenuDTOItem(self::REFERENTIEL, 'menu.header.referentiel');

        foreach (self::FAMILLES as [$code, $libelle, $glyphe, $route, $parametres]) {
            $referentiel->addItem(
                (new MenuDTOItem($code, $libelle, $route))
                    ->setIcon($glyphe)
                    ->setIconColor(TypographyTextColorEnum::PRIMARY)
                    ->setRouteParameters($parametres),
            );
        }

        /*
         * La liste est composée ici plutôt que relue par `MenuDTO::getItems()` :
         * l'accesseur du portail rend un `array` sans forme déclarée, et rien ne
         * garantirait alors que les entrées sortent dans l'ordre.
         *
         * @var list<MenuDTOItem> $entrees
         */
        $entrees = [
            new MenuDTOItem(self::ACCUEIL, 'menu.header.accueil', 'app_mdm_tableau_de_bord'),
            $referentiel,
        ];

        foreach (self::ENTREES as [$code, $libelle, $route, $parametres]) {
            $entrees[] = (new MenuDTOItem($code, $libelle, $route))->setRouteParameters($parametres);
        }

        foreach ($entrees as $entree) {
            if (isset(self::NOTIFICATIONS[$entree->code])) {
                $entree->setNotification(self::NOTIFICATIONS[$entree->code]);
            }
        }

        return $entrees;
    }

    /**
     * L'utilisateur identifié.
     *
     * `UserDTO::mock()` du portail pointe sur `/img/mock/avatar.png` ; le
     * fichier est déposé à cet emplacement exact plutôt que corrigé ici, pour
     * que tout composant importé qui s'y réfère fonctionne sans retouche — le
     * profil du menu mobile le construit lui-même, hors de notre portée.
     */
    public static function utilisateur(): UserDTO
    {
        return UserDTO::mock();
    }
}
