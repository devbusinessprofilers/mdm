# Intégration des maquettes

Source : projet Claude Design `c8869b33-db2b-4906-9074-f833df144fd0`, qui
contient deux handoffs distincts :

| Handoff | Périmètre | Section |
|---|---|---|
| `Espace prestataire.dc.html` | portail public prestataire | [Espace prestataire](#espace-prestataire) |
| `MDM Business Profilers.dc.html` | back-office du référentiel | [Back-office MDM](#back-office-mdm) |

Ce document ne couvre que l'**intégration front**. Aucune logique métier,
aucune authentification : les contrôleurs se contentent de rendre les gabarits
et les formulaires ne sont pas traités.

Les deux handoffs partagent le même socle : `assets/styles/tokens.css`.

---

# Espace prestataire

Handoff `Espace prestataire.dc.html`.

## Groupe livré : « Connexion & mot de passe »

| Écran de la maquette | Nœud Figma | Route | Gabarit |
|---|---|---|---|
| Connexion | `36:1455` | `/connexion` | `auth/connexion.html.twig` |
| Mot de passe | `74:1914` | `/connexion/mot-de-passe` | `auth/mot_de_passe.html.twig` |
| Mot de passe — user défaut | `392:24354` | `/connexion/mot-de-passe-defaut` | `auth/mot_de_passe_defaut.html.twig` |
| Mot de passe oublié | `74:2380` | `/mot-de-passe-oublie` | `auth/mot_de_passe_oublie.html.twig` |
| Création du mot de passe | `74:2093` | `/creation-mot-de-passe` | `auth/creation_mot_de_passe.html.twig` |
| **Mail d'invitation** | `S4.MailInvitation` | — | **non livré, voir plus bas** |

---

## Organisation des fichiers

```
assets/
  styles/
    tokens.css        variables de la charte (couleurs, typo, espacements)
    components.css    bouton, champ, tag, avatar, pastille, jauge, séparateur
    auth.css          mise en page des écrans de connexion
  controllers/
    bp_password_controller.js   bascule d'affichage du mot de passe
  images/auth/
    avatar-user.png   photo de l'utilisateur identifié

templates/auth/
  base.html.twig        coquille commune (carte + visuel + panneau)
  _composants.html.twig macros Twig : bouton, champ, tag, avatar, icônes, jauge

src/Account/Controller/AuthController.php   les 5 routes
```

Les feuilles de style sont chargées par `assets/app.js`, dans cet ordre :
`tokens` → `components` → `app` → `auth`.

## Utiliser les composants

```twig
{% import 'auth/_composants.html.twig' as ui %}

{{ ui.bouton('Continuer', { style: 'primary', size: 'lg', block: true }) }}
{{ ui.bouton('Annuler', { style: 'outline', href: path('...') }) }}

{{ ui.champ('Email', 'email', {
    type: 'email', placeholder: 'Votre e-mail professionnel', required: true
}) }}

{{ ui.avatar('photo', 72) }}
{{ ui.regle('8 caractères', 'valide') }}
{{ ui.jauge(49, 'moyen') }}
```

Les 9 variantes du composant Figma `Button` sont disponibles via les classes
`bp-btn--primary|outline|text` × `bp-btn--lg|sm`, plus l'état désactivé.

---

## Écarts assumés par rapport à la maquette

Chaque écart est également commenté à l'endroit concerné dans le CSS
(rechercher `ÉCART`).

| # | Constat dans la maquette | Décision d'intégration |
|---|---|---|
| 1 | Les libellés « Email* » et « Saisissez votre mot de passe* » sont en **blanc** sur la carte blanche — invisibles. L'écran « Mot de passe oublié » les affiche pourtant en `--neutral-900`. | Libellés en `--neutral-900` sur tous les écrans. |
| 2 | L'icône œil des champs mot de passe est en `--neutral-100` (blanc) sur fond clair — invisible. | Passée en `--neutral-500`. |
| 3 | Le bouton change de police entre l'état défaut (Ubuntu Sans 12/20) et l'état survol (Lato 14/22) : le libellé sauterait au passage de la souris. | Application du token `--texte-button` du design system (Lato 900 14/22) sur tous les états. |
| 4 | Le survol du bouton « outline » est décrit comme un aplat blanc, ce qui supprime la bordure et n'offre aucun retour visuel. | Bordure conservée, fond teinté en `--primary-bleu-clair`. |
| 5 | Le texte indicatif des champs est en `--neutral-900` : un champ vide paraît rempli. | Placeholder en `--neutral-500`. |
| 6 | L'ombre portée de la carte est absente du seul écran « Connexion », présente sur les trois autres. | Ombre appliquée partout. |
| 7 | Aucun état de focus n'est décrit. | Anneau de focus ajouté sur les boutons et les champs (RGAA 10.7). |
| 8 | « Mot de passe oublié ? » est un texte simple. | Rendu comme un lien vers `/mot-de-passe-oublie`. |

Points restés fidèles bien qu'inhabituels, à confirmer par le design :
le bouton `outline` en taille `sm` porte un anneau **noir** de 2 px alors que la
taille `lg` utilise un dégradé turquoise.

---

## Ce qui manque, et pourquoi

### Les photos des panneaux de gauche

Les 4 photos JPEG du handoff **n'ont pas pu être récupérées** : l'API de lecture
du projet Design plafonne à 256 Ko par fichier, soit ~192 Ko de binaire, et les
4 fichiers dépassent ce seuil. Ils reviennent tronqués, sans marqueur de fin.

En attendant, `.auth-card__visual-media` retombe sur un dégradé de marque.

**Pour brancher les vraies photos**, déposer les fichiers dans
`assets/images/auth/` puis ajouter dans `auth.css` :

```css
.auth-card--connexion {
    --auth-visual-image: url('../images/auth/connexion.jpg');
    --auth-visual-position: 152.992% 45.876%;
    --auth-visual-size: 121.387% 89.648%;
}
```

Cadrages relevés dans la maquette :

| Écran | `background-position` | `background-size` |
|---|---|---|
| Connexion | `152.992% 45.876%` | `121.387% 89.648%` |
| Mot de passe | `38.227% 45.050%` | `120.795% 89.221%` |
| Mot de passe — user défaut | `66.912% 45.876%` | `121.387% 89.648%` |
| Mot de passe oublié | `center` | `cover` |
| Création du mot de passe | `154.707% 55.711%` | `123.290% 91.053%` |

Fichiers d'origine attendus, dans `screens/assets/` du projet Design :
`07fc53f89347084c.jpg`, `ca0f3d7c2606a6ad.jpg`, `4a81360010d4f039.jpg`,
`51be908367e4bf74.jpg`, `8ddd06934a17badf.jpg`.

### Le mail d'invitation

`S4.MailInvitation` vit dans `screens4/Components.bundle.js`, un fichier de
**271 Ko** qui dépasse lui aussi le plafond de lecture de 256 Ko. Le composant
se situe au-delà du point de troncature et reste donc inaccessible.

Deux façons de débloquer : faire scinder ce bundle côté Design, ou récupérer
l'écran par un autre canal (export direct, capture annotée).

---

## Adaptation aux petits écrans

Le handoff ne fournit que des maquettes desktop (1920 × 1080). En dessous de
1280 px, le visuel de gauche est masqué et le panneau passe en flux normal,
limité à 560 px. Sous 600 px, les titres basculent sur les tokens
`--mobile-titre-*`. **Ces règles sont une proposition d'intégration, pas une
reprise de maquette** : à valider par le design.

## Polices

Chargées depuis Google Fonts, comme dans le handoff (Lato, Ubuntu Sans, Roboto).
À arbitrer avant mise en production : les héberger localement supprime un appel
tiers et la question RGPD associée.

---

# Back-office MDM

Handoff `MDM Business Profilers.dc.html`.

## Écrans livrés

| Écran de la maquette | Identifiant | Route |
|---|---|---|
| Mon espace de travail | `accueil` | `/espace-de-travail` |
| Liste des fiches | `liste` | `/referentiel?etat=nominal` |
| Liste des fiches · Lieux | `liste` | `/referentiel/lieux` |
| Éditeur de fiche Lieu | `fiche` | `/referentiel/lieux/fiche?section=0..15` |
| Création d'une fiche | `creation` | `/referentiel/fiche/nouvelle?etat=vierge` |

---

## « Mon espace de travail »

| Rôle | URL |
|---|---|
| Supply (défaut) | `/espace-de-travail` |
| Administrateur | `/espace-de-travail?role=admin` |
| Chef de projet | `/espace-de-travail?role=cp` |

Un rôle inconnu retombe silencieusement sur Supply.

## Organisation des fichiers

```
assets/
  styles/
    app-shell.css   barre supérieure, rail de navigation, pastilles, pilules
    workspace.css   écran « Mon espace de travail »
  images/brand/
    bp-mark.png     rond de marque

templates/mdm/
  base.html.twig          coquille : header + rail + zone de contenu
  _composants.html.twig   macros : icônes, pastille, entrée de rail, entrée de nav
  espace_travail.html.twig

src/Pim/Controller/EspaceTravailController.php
src/Pim/Maquette/EspaceTravailMaquette.php   contenu de démonstration
```

`EspaceTravailMaquette` porte les données des trois rôles telles que définies
dans la maquette. **C'est du contenu jetable** : il disparaîtra dès qu'un
service métier alimentera l'écran.

## Branchement des liens

Règle appliquée partout : **un lien n'existe que s'il a une destination.**

- Écran intégré → on lui passe `href`, c'est un `<a>` normal.
- Écran pas encore intégré → pas de `href`. La macro rend un élément inerte
  (`role="link"`, `aria-disabled="true"`, infobulle « Écran non encore
  intégré ») au lieu d'un `href="#"`, qui remonterait la page et laisserait
  croire que la destination existe.
- Une **action** (Importer, Exporter, Nouvelle fiche, Contacter le support)
  n'est pas un lien : elle est rendue en `<button type="button">`.

L'apparence au repos est identique dans les deux cas — seuls le curseur et le
retour au survol changent — pour ne pas s'écarter de la maquette.

**Brancher un écran fraîchement intégré = ajouter `href: path('sa_route')` à
son appel de macro.** Rien d'autre à toucher.

État actuel du chrome :

| Élément | Destination |
|---|---|
| Logo | `app_mdm_espace_travail` |
| Nav · Accueil | `app_mdm_espace_travail` |
| Nav · Référentiel | `app_mdm_referentiel_general` |
| Rail · Mon espace de travail | `app_mdm_espace_travail` |
| Rail · Toutes les fiches | `app_mdm_referentiel_general` |
| 13 autres entrées de rail, 5 autres entrées de nav | en attente d'intégration |

## Ajouter un écran au back-office

```twig
{% extends 'mdm/base.html.twig' %}
{% set ecran = 'qualite' %}   {# marque l'entrée active du rail et de la nav #}

{% block contenu %}
    …
{% endblock %}
```

Identifiants d'écran reconnus par le rail : `accueil`, `general`, `liste`,
`fiche`, `salles`, `qualite`, `sync`, `admin`, `composants`, `medias`,
`imports`.

## Écarts assumés

| # | Constat dans la maquette | Décision d'intégration |
|---|---|---|
| 1 | L'en-tête affiche un logo de 200 × 40 (`assets/bp-logo.png`) que le plafond de lecture de 256 Ko rend inaccessible. | Lockup composé du « mark » rond (récupéré, 91 Ko) et du nom en texte. Pour revenir au logo d'origine : masquer `.mdm-logo__wordmark` et remplacer l'image de `.mdm-logo__mark`. |
| 2 | Le champ de recherche est un libellé statique. | Rendu en `<input type="search">` avec libellé accessible. |
| 3 | Le tableau des priorités est une pile de `div`. | Rendu en `<table>` : c'est un tableau de données, son en-tête doit être annoncé par un lecteur d'écran. Largeurs de colonnes inchangées. |
| 4 | Le sélecteur de rôle est un bouton d'état local. | Rendu en liens, l'écran étant produit côté serveur pour chaque rôle. |
| 5 | Aucun état de focus n'est décrit. | Anneau de focus sur le sélecteur de rôle et le champ de recherche. |

Point resté fidèle mais discutable : le sous-titre de l'utilisateur dans
l'en-tête affiche « Équipe Supply » quel que soit le rôle sélectionné, comme
dans la maquette. À confirmer par le design.

## Le rail ne défile jamais

Exigence explicite : `overflow: hidden` sur `.mdm-rail`, comme la maquette le
déclare. Seule `.mdm-content` défile.

Cela pose un problème que la maquette ne rencontre pas. Son cadre fait 1080 px
de haut, ce qui laisse 1008 px au rail pour un contenu de 974 px : ça passe. Un
navigateur réel, lui, n'offre qu'environ 930 px de viewport sur un écran
1080p — les dernières entrées seraient purement coupées, donc inatteignables.

Le rythme vertical du rail est donc indexé sur la hauteur disponible, via des
`clamp()` en `100vh` : **valeurs exactes de la maquette à 1080 px**, puis
compression linéaire jusqu'à 640 px. Seuls les espacements bougent — padding
des entrées, écart entre groupes, marge du rail, interligne des intitulés.
Tailles de texte, icônes et couleurs restent identiques.

Couverture mesurée au navigateur (les 15 entrées atteignables, sans ascenseur) :

| Fenêtre | Viewport | Place pour le rail | Contenu | Tient |
|---|---|---|---|---|
| 1920 × 1200 | 1053 px | 981 px | 981 px | oui |
| 1920 × 1080 | 932 px | 860 px | 860 px | oui |
| 1920 × 960 | 813 px | 741 px | 741 px | oui |
| 1920 × 900 | 753 px | 681 px | 681 px | oui |
| 1920 × 820 | 673 px | 601 px | 601 px | oui |
| 1366 × 768 | 621 px | 549 px | 549 px | oui |
| 1280 × 720 | 573 px | 501 px | 530 px | **non, 29 px coupés** |

Les viewports du tableau sont ceux mesurés sous Selenium, dont le chrome
navigateur consomme ~147 px : sur un poste réel la marge est plus confortable
à résolution égale.

En dessous de 1280 × 720, faire tenir 15 entrées et 4 intitulés sans ascenseur
supposerait de changer la structure du rail — groupes repliables, ou moins
d'entrées visibles par défaut. À arbitrer avec le design si ces postes font
partie du parc.

---

## « Liste des fiches »

`/referentiel` · `/referentiel/lieux` · gabarit `mdm/liste_fiches.html.twig` ·
`assets/styles/liste.css` · `liste_controller.js` · `ListeFichesMaquette`

Un seul écran remplace le référentiel général **et** la page Lieux. Le panneau
de filtres porte la gamme, et les colonnes suivent : « Type » devient
« Catégorie de lieu » quand la liste ne porte qu'une gamme, et un neuvième
groupe de facettes — la taxonomie — apparaît. `/referentiel/lieux` reste servi
et ouvre la liste déjà filtrée, pour que les liens du rail continuent de
marcher.

### La sémantique de facettes

C'est le point dur du handoff, et il est explicite dans son code :
**intersection entre groupes, union à l'intérieur d'un groupe**. Un seul
prédicat sert les lignes, les badges et le compte — les trois ne peuvent pas
dire autre chose.

Le volume affiché n'est pas le nombre de lignes rendues : c'est le plus petit
total de groupe retenu. Un échantillon de 36 fiches ne peut pas prétendre en
compter 15 906, et la maquette ne le prétend pas non plus.

| Filtre | Volume annoncé | D'où il vient |
|---|---|---|
| Publiée + France | 15 906 | min(15 906 ; 16 210) |
| Lieux + Publiée + France | 12 480 | min(12 480 ; 15 906 ; 16 210) |
| Valeurs IA + Anomalies | 231 | 200 + 31, un seul groupe actif |

### Les treize états

Servis par `?etat=`, comme les autres écrans. Par défaut `nominal`.

| `?etat=` | Ce qu'il montre |
|---|---|
| `nominal` | gammes mélangées, 8 groupes de facettes |
| `lieux` | filtré sur Lieux : 9 groupes, colonne « Catégorie de lieu » |
| `selection` | 4 lignes cochées, barre d'actions, menu « Plus d'actions » ouvert |
| `tout` | le filtre entier retenu — 15 906 — et l'avertissement de volume |
| `seuil` | même sélection, actions au-delà de leur plafond |
| `socle` | valeurs IA et conflits dans la colonne polymorphe, recherche active |
| `modifiee` | vue enregistrée touchée : « Enregistrer » ou « Réinitialiser » |
| `replie` | panneau rangé à 60 px, gardant le compte de filtres |
| `picker` | sélecteur de vues déployé |
| `rien` | 5 filtres, aucun résultat — distinct d'un référentiel vide |
| `chargement` | dix squelettes de lignes |
| `vues` | modale de gestion des vues |
| `compacte` | lignes à 40 px, 19 par page au lieu de 14 |

### Trois règles que la maquette encode

- **Une photo absente n'est pas une anomalie.** C'est un champ « vide et
  obligatoire » du socle : l'anneau pêche de la vignette le porte, le glyphe
  d'alerte reste réservé aux conflits.
- **Une grande sélection n'est pas une petite en plus gros.** Passé son
  plafond — 5 000 pour « Publier », 500 pour « Envoyer les accès » — l'action
  est désactivée et réclame une confirmation d'un autre ordre. L'irréversibilité
  est un fait distinct : elle occupe son propre emplacement et **survit** au
  plafond, elle n'est pas écrasée par lui.
- **Un filtre sans résultat n'est pas un référentiel vide.** Le message rappelle
  les 18 953 fiches et propose de retirer un filtre, pas de repartir de zéro.

L'identifiant d'une fiche se dérive de son rang fixe, pas de sa position dans la
page : il reste stable d'un filtre et d'une page à l'autre.

### Écarts assumés

| # | Constat dans la maquette | Décision d'intégration |
|---|---|---|
| 1 | Le fichier embarque son chrome de démonstration : colonne d'états de 300 px, contrôles de zoom, cadre de 1920 × 1080 mis à l'échelle. | Non intégré — outillage du handoff. Les états passent par `?etat=`. |
| 2 | Cocher une facette ne recalcule rien dans le fichier : `onClick: () => {}`. | Le clic marque l'intention et rien de plus. Recalculer côté client ferait diverger les lignes, les badges et le compte, qui sortent d'un seul prédicat côté serveur. |
| 3 | La densité, le repli du panneau et les menus sont des états React. | La densité et le repli passent par `?etat=` (ils changent la pagination) ; les menus et la sélection de lignes restent locaux. |
| 4 | Les cartes de répartition par typologie du référentiel général ont disparu. | Suivies : la nouvelle maquette ne les porte plus. |
| 5 | Le tri, la recherche et le choix de colonnes sont des libellés statiques. | Reproduits tels quels. |

### Ce que cette révision a supprimé

`mdm/_liste_fiches.html.twig`, `mdm/referentiel_general.html.twig`,
`mdm/lieux.html.twig` et `assets/styles/referentiel.css` n'ont plus d'emploi :
la nouvelle maquette ne reprend ni leur table, ni leurs cartes de typologie.
`ReferentielMaquette` est conservée — c'est elle qui alimente la modale
d'édition rapide, toujours ouverte par le crayon de chaque ligne.

## Le logo

`assets/images/bp-logo.png` — le lockup « bp Business Profilers » manquant,
enfin fourni. Il remplace le montage provisoire (pastille ronde + nom en texte).

Le fichier est **monochrome blanc sur fond transparent**, 1349 × 246 : posé tel
quel sur l'en-tête blanc, il serait invisible. Il sert donc de **masque CSS** et
c'est le marine qui le peint — la couleur exacte du nom qu'il remplace. Le
procédé est sans perte et suit le jeton : si l'en-tête passe un jour au sombre,
une seule déclaration change.

Si une version couleur existe, elle se substitue en remplaçant le fichier et en
troquant le masque contre un `background-image`.

## « Éditeur de fiche Lieu »

`/referentiel/lieux/fiche?section=0..15` · gabarit `mdm/fiche_lieu.html.twig`
et partiels `mdm/fiche/_*.html.twig`.

C'est le plus gros écran du handoff : 16 sections, 124 champs, six blocs
conditionnels et un rail qui change de contenu.

### Données générées, pas recopiées

`src/Pim/Maquette/FicheLieuDonnees.php` (1 884 lignes) est **généré** en
évaluant le préambule du handoff : les 16 sections, leurs champs, leurs
autorités, les groupes de puces et les 38 valeurs suggérées y sont repris tels
quels. Recopier 124 champs à la main aurait introduit des écarts ; la
génération garantit la fidélité. **Ne pas éditer ce fichier — le régénérer si
la maquette évolue.**

`FicheLieuMaquette.php` porte la logique de présentation : quelles sections
affichent quel bloc, quelles suggestions l'IA propose, la matrice de capacités.

### Ce que porte chaque section

| Bloc | Sections concernées |
|---|---|
| Grille de champs | toutes sauf 8, 11, 12, 14 |
| Puces | 8 · Services & équipements |
| Formules de visibilité | 12 · Booster ma visibilité |
| Galerie de médias | 11 · Médias |
| Disponibilités | 0 · Informations générales, 6 · Restauration |
| Matrice de capacités | 5 · Réunion |
| Tableau + panneau latéral | 14 · Collaborateurs |
| Suggestions IA, canaux, historique | toutes |

Le rail bascule sur les 16 sections de la fiche, réparties en « Ma fiche »
(12) et « Paramètres » (4), avec leur taux de complétude. Ces taux sont
**calculés** — champs renseignés sur champs totaux, ou puces cochées pour les
sections qui n'ont que des puces — comme dans la maquette.

La matrice de capacités a ses deux états : lecture (`?section=5`) et édition
(`?section=5&capacites=edition`), qui ajoute les champs saisissables, les cases
à cocher, l'ajout de salle et le bloc de pré-remplissage par l'IA.

### Section 14 · Collaborateurs

C'est la seule section qui **remplace** la grille de champs au lieu de s'y
ajouter. Elle porte deux blocs propres :

- un tableau en grille CSS de dix colonnes — `84 96 84 1fr 116 104 78 78 78 62`,
  seul l'email est élastique — dont quatre pastilles de droit : disque vert
  coché ou cercle gris barré ;
- un panneau latéral de 420 px, à droite de la barre d'actions, qui occupe
  toute la hauteur de l'écran.

Le contact principal n'a pas de corbeille : il faut d'abord désigner quelqu'un
d'autre. C'est la règle `canDelete: !main` de la maquette.

Le panneau a deux états, tous deux rendus dans le DOM et permutés par le
contrôleur `collaborateurs` :

| État | Ouvert par | Contenu |
|---|---|---|
| Invitation | état initial, retour depuis « Annuler » | adresse email seule, puis « Continuer » |
| Formulaire | « Continuer », ou le crayon d'une ligne | avatar, 4 champs, rôle, 3 droits, statut |

Le crayon pré-remplit le formulaire avec la ligne cliquée. Comme dans la
maquette, **les droits et le statut repartent à zéro** à chaque ouverture : ils
ne sont pas portés par la ligne du tableau, seulement par l'état du panneau.
L'email est en lecture seule des deux côtés — c'est la clé de l'invitation.

Deux molécules divergent ici de leurs homonymes ailleurs dans le produit, et
ce sont bien deux spécifications distinctes du handoff, pas un oubli :

| Molécule | Ailleurs | Panneau collaborateur |
|---|---|---|
| Case à cocher | `bp-checkbox`, 20 px, angles vifs | `collab__case`, 18 px, coins à 4 |
| Interrupteur | `fiche__bascule`, 40 × 24, anneau turquoise | `collab__bascule`, 38 × 20, sans anneau |

### Écarts assumés

| # | Constat | Décision |
|---|---|---|
| 1 | « - » compte comme une valeur absente pour les suggestions IA, mais s'affiche tel quel dans le champ. | Les deux règles sont reproduites séparément — c'est bien ce que fait la maquette. |
| 2 | Le nombre de champs de la section s'affiche en chiffre nu, sans unité, à côté du titre. | Reproduit tel quel. À confirmer par le design : « 6 champs » serait plus clair. |
| 3 | Aucun état de focus n'est décrit. | Anneaux de focus ajoutés sur les boutons et contrôles. |
| 4 | Les quatre formules de « Booster ma visibilité » sont identiques dans la maquette, seule la deuxième est marquée sélectionnée. | Reproduit tel quel : le contenu des quatre paliers n'est pas encore arbitré. |
| 5 | Sur « Collaborateurs », les deux boutons du bas du panneau s'appellent « Continuer » et « Annuler », et tous deux ramènent à l'écran d'invitation. | Reproduit tel quel. À confirmer : « Enregistrer » serait attendu sur le premier. |
| 6 | Le rouge de l'astérisque obligatoire — `rgb(255,107,107)` — n'est ni `--secondary-rouge` ni `--feedback-error`. | Ajouté en `--mdm-obligatoire`, aux côtés des autres teintes que le système ne nomme pas. |

### Ce qui manque encore

- **Les 13 glyphes de `capa-icons.js`** au-dessus des colonnes de la matrice de
  capacités. Les intitulés (m³, Théâtre, Réunion, En U, Classe, Cabaret,
  Banquet, Cocktail, Lumière naturelle, Climatisé, PMR, Espace dansant, Plan)
  sont en place aux bonnes largeurs ; seules les icônes sont absentes.
- **`assets/salle.jpg`**, la photo des vignettes de salle : l'emplacement est
  au bon gabarit, teinté en attendant.

---

## Menus déroulants des champs liste

Les 19 champs de type `sel` de l'éditeur de fiche portent désormais le menu
déroulant du design system. Molécules reprises de `ds/components-part2.js` et
`ds/components-part3.js` :

| Molécule | Implémentation |
|---|---|
| `MenuDropdown` type `list checkboxes` | `.bp-menu` — 210 px |
| `MenuDropdown` type `poi` | `.bp-menu--poi` — 432 px |
| `MenuListItem` (select oui/non) | `.bp-menu-item` |
| `Checkboxes` (type × state) | `.bp-checkbox`, 4 états |

La barre de défilement du composant — piste blanche, curseur turquoise de
8 px — fait partie de la molécule et est reproduite.

Le comportement (ouvrir, fermer au clic extérieur ou à Échap) est porté par
`assets/controllers/bp_select_controller.js`. Aucune sélection n'est
enregistrée : l'intégration s'arrête à l'affichage.

### D'où viennent les options

Deux origines, à ne pas confondre :

| Champ | Source | Autorité |
|---|---|---|
| Points d'intérêt / Aéroport(s) | variante `poi` du handoff — quatre aéroports, un sélectionné | **réelle** |
| Les 17 autres champs `sel` | `FicheLieuMaquette::CATALOGUE` | **inventée** |

⚠️ **Le catalogue est du contenu de démonstration**, écrit à la demande pour
que les menus soient présentables. Les valeurs sont plausibles (régions
françaises, classements hôteliers, typologies de restaurant…) mais **n'ont
aucune autorité métier**. Elles disparaissent au branchement du service de
taxonomies — c'est le seul point à rebrancher, et il est isolé dans une seule
constante.

La sélection est déduite de la valeur du champ, découpée sur les virgules pour
les champs multivalués — « Brunch, Déjeuner assis, Dîner assis » coche bien
trois entrées. Un champ sans catalogue retombe sur sa propre valeur.

Les listes longues défilent : hauteur plafonnée à 240 px, barre native masquée
au profit du rail de la molécule. « Région » et ses 13 entrées le démontrent.

### Écarts assumés

| # | Constat | Décision |
|---|---|---|
| 1 | La molécule est présentée isolée dans la bibliothèque : ni positionnement, ni ombre. | Ancrée sous son champ (`position: absolute`, décalage 4 px) et ombre des panneaux, pour qu'elle se détache du contenu. |
| 2 | La molécule fixe 210 px (`list checkboxes`) et 432 px (`poi`), alors que les champs de la fiche font 694 px. Le menu flottait sous un champ trois fois plus large. | **Le menu prend la largeur de son champ** (`width: 100%`). La variante `poi` ne se distingue plus que par sa teinte. |
| 3 | Les libellés sont en `nowrap` : « Aéroport Inter. Amiens Henry Potez - Albert Méaulte (40 km) » était coupé net. | **Le libellé s'enroule**, l'entrée grandit. La case reste alignée sur la première ligne. |

Les points 2 et 3 ont été arbitrés avec le demandeur.
