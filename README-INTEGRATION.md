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
| Référentiel général | `general` | `/referentiel` |
| Lieux | `liste` | `/referentiel/lieux` |
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

## « Référentiel général »

`/referentiel` · gabarit `mdm/referentiel_general.html.twig` ·
données `src/Pim/Maquette/ReferentielMaquette.php` (jetable, comme celles de
l'espace de travail).

Les 40 lignes sont produites par le même algorithme que la maquette — mêmes
noms, mêmes rotations de typologie, ville, statut, complétude, source et
canaux. Reproduire la génération plutôt que figer 40 lignes garde le fichier
lisible et le rendu identique.

Largeurs de colonnes, reprises telles quelles :

| Colonne | Largeur |
|---|---|
| Sélection | 52 px |
| Fiche | reste disponible |
| Typologie | 176 px (famille) · 210 px (sous-typologie) |
| Ville | 160 px |
| Statut | 160 px |
| Complétude | 180 px |
| Source | 130 px |
| Dernière modification | 150 px |
| Actions | 48 px |

La colonne **Canaux** a été retirée par la maquette ; **Actions** l'a remplacée
en fin de ligne. Voir « Révision du tableau » plus bas.

Trois pièges rencontrés, réglés, qui vaudront pour les prochaines tables :

- **Un libellé `.sr-only` dans une cellule allonge le document.** `.sr-only`
  est en `position: absolute` ; si aucun parent n'est positionné, son bloc
  conteneur est le bloc conteneur initial : il échappe à `overflow: hidden` et
  compte dans la hauteur de la page. Les 40 libellés du crayon d'édition
  rapide, hauts de 1 px, portaient ainsi le document à 2 143 px pour un
  viewport de 932 — d'où un grand vide sous les deux écrans de liste. Le shell
  `.mdm-app` est désormais `position: relative`, et le crayon aussi. À
  surveiller pour tout `.sr-only` posé dans une zone défilante. Les libellés de
  l'en-tête y échappaient : `thead th` est `sticky`, donc déjà positionné.

- **`box-sizing: border-box` est obligatoire sur les cellules.** Sans lui, le
  `padding-right` de 12 px s'ajoute à la largeur déclarée : chaque colonne
  gagnait 12 px et la dernière était écrasée à 64 px. La maquette le précise
  dans son helper de colonne. La table de l'espace de travail avait le même
  défaut, corrigé au passage.
- **La colonne de sélection vaut 52 px, pas 32.** La maquette pose 20 px de
  retrait de ligne, puis une case de 16 px suivie de 16 px de marge. En
  `border-box`, le retrait fait partie de la largeur de la colonne.

### Écarts assumés

| # | Constat dans la maquette | Décision d'intégration |
|---|---|---|
| 1 | La table est une pile de `div`. | Rendue en `<table>`, en-tête figé par `position: sticky` là où le handoff le sortait de la zone défilante. Largeurs et hauteurs de ligne inchangées. |
| 2 | L'écran redéfinit ses propres boutons en Ubuntu Sans 12/20. | Réutilisation du composant `bp-btn` du design system, déjà aligné sur le token `--texte-button`. |
| 3 | La recherche et les filtres sont des libellés statiques. | Champ `<input type="search">` réel et boutons avec `aria-pressed`. |
| 4 | Trois lignes portent une case cochée alors que la barre d'actions groupées est masquée sur cet écran. | Reproduit tel quel — incohérence à confirmer par le design. |
| 5 | Aucun état de focus n'est décrit. | Anneau de focus sur les filtres, la vue enregistrée et la pagination. |

### Adaptation aux petits écrans

La table conserve sa largeur utile (`min-width: 1400px`) et défile
horizontalement plutôt que d'écraser ses colonnes. Les cartes de typologie
passent de 5 à 3 colonnes sous 1600 px, puis à 2 sous 1100 px. Proposition
d'intégration, à valider par le design.

---

## « Lieux »

`/referentiel/lieux` · gabarit `mdm/lieux.html.twig`.

Cet écran et le référentiel général partagent la même table. Elle est extraite
dans **`mdm/_liste_fiches.html.twig`**, qui rend la barre de filtres, le
bandeau de sélection facultatif, la table et la pagination. Chaque écran
fournit ses données et se contente d'inclure le partiel :

```twig
{% include 'mdm/_liste_fiches.html.twig' with {
    recherche: entete.recherche,
    vue_enregistree: entete.vueEnregistree,
    note: entete.note,
    selection: true
} %}
```

Les colonnes sont décrites côté PHP (`ReferentielMaquette::colonnes()`) sous la
forme `{cle, libelle}` : le partiel boucle dessus, l'ordre et les intitulés
viennent de la maquette, les largeurs des classes `.ref__col-<cle>`. Ajouter
une liste (Restaurants, Activités…) revient à décrire ses colonnes et ses
lignes, sans toucher au gabarit.

Ce qui distingue l'écran Lieux du référentiel général :

| | Référentiel général | Lieux |
|---|---|---|
| Lignes | 40, toutes typologies | 50, typologie « Lieux » |
| Cartes de répartition | oui | non |
| Colonne typologie | famille, 176 px, avec pastille | sous-typologie, 210 px, après la ville |
| Bandeau de sélection | masqué | affiché, 5 actions groupées |
| Lignes sélectionnées | case cochée seulement | case cochée **et** ligne teintée |
| Vue enregistrée | « Toutes les fiches » | « Lieux à enrichir · FR » |
| Pagination | 474 pages | 250 pages |

### Mise à jour du handoff

La maquette a été révisée entre l'intégration du référentiel général et celle
des Lieux (1 403 → 2 052 lignes, deux écrans ajoutés : éditeur de fiche et
configuration des salles). Les largeurs de colonnes ont changé : le nom devient
élastique, Canaux passe de 180 à 160, Source de 130 à 112, et Dernière
modification devient fixe à 192 px. **Les deux écrans ont été alignés sur cette
nouvelle version.**

Au passage, la révision a introduit une incohérence côté maquette : l'en-tête
déclare la colonne du nom en élastique alors que les cellules du corps la
gardent en 340/360 px fixes — en-tête et corps seraient décalés d'une vingtaine
de pixels. L'intégration en `<table>` y échappe : les largeurs sont portées par
l'en-tête et le corps suit.

### Révision du tableau

La maquette a de nouveau revu la table des deux listes :

| | Avant | Après |
|---|---|---|
| Canaux | 180 px, quatre pastilles MP/ST/SF/PP | **colonne retirée** |
| Source | 130 px | 130 px |
| Dernière modification | 192 px | **150 px** |
| Actions | — | **48 px, crayon d'édition rapide** |

Les données des canaux existent toujours côté maquette : seule la colonne
disparaît. `ReferentielMaquette::canaux()` est donc conservé, prêt à resservir.

Les 160 px libérés ont permis de **revenir à la complétude de la maquette** :
barre à gauche, taux à droite sur 40 px en Lato 900 / 14 px. L'intégration
l'avait provisoirement empilé sur deux lignes pour tenir dans 120 px.

En-tête et corps restent alignés d'office : la table est un `<table>`, les
largeurs sont portées par l'en-tête. La maquette, elle, donne 30 px de retrait
à droite de son en-tête contre 48 px au corps — un décalage de 18 px sur la
dernière colonne, qui n'est pas reproduit.

## Modale « Édition rapide »

`mdm/_edition_rapide.html.twig` · `assets/styles/edition-rapide.css` ·
`edition_rapide_controller.js`

Le crayon d'une ligne l'ouvre. **Une seule instance par page** : le crayon
transmet sa ligne en paramètres Stimulus, le contrôleur remplit l'en-tête, la
gamme et l'adresse. À 50 lignes, rendre 50 modales n'aurait pas de sens.

L'adresse est résolue depuis la ville de la ligne, par la table
`ReferentielMaquette::ADRESSES` — 26 villes, avec rue, code postal et GPS,
exactement comme le fait la maquette.

### Les quatre états

Portés par des classes sur la racine, ce qui les rend inspectables sans
manipuler la page :

| Classe | Déclencheur | Effet |
|---|---|---|
| `qe--adresse` | « Corriger » | champ de recherche et panneau de suggestions à la place de la carte d'adresse |
| `qe--sites-ouverts` | « Gérer » | panneau des 31 sites de diffusion |
| `qe--enregistrement` | « Enregistrer » | corps à 50 %, jauge sous l'en-tête, rouet, retour à la liste après 1,1 s |
| `qe--erreur` | **aucun** | anneau pêche sur le nom, message sous le champ, alerte en pied |

`qe--erreur` n'a pas de déclencheur **dans la maquette non plus** : l'état
existe, aucune interaction n'y mène. Il est intégré et vérifié, il s'obtient en
posant la classe.

### Ce qui est recalculé

Le panneau des sites est le seul endroit où la maquette recompte quelque chose.
Cocher ou décocher met à jour, comme elle : le compte global (« 9 sur 31 »), les
quatre puces de résumé et leur « +N », et le décompte de chaque groupe — deux
fois, dans la liste et sous le champ. Le site marqué « obligatoire » refuse le
clic.

Les raccourcis de la maquette sont câblés : Échap ferme la couche la plus haute
(confirmation, puis panneau, puis modale), ⌘/Ctrl + ⏎ enregistre, Alt + ← / →
passent d'une fiche à l'autre.

### Écarts assumés

| # | Constat dans la maquette | Décision d'intégration |
|---|---|---|
| 1 | La modale est posée en absolu à 340 / 116 dans un cadre de 1920 × 1080. | C'est exactement le centre du cadre : elle est centrée, plafonnée à 1240 × 848. Rendu identique à cette taille, et elle tient plus bas. |
| 2 | Les deux panneaux flottants sont en absolu et la maquette réserve 150 px en pied du corps. | Suffisant à 1080 px, pas en dessous. Le pied est conservé et complété par une réserve exacte sur le bloc porteur. Le pied du panneau des sites reste atteignable jusqu'à 768 px de haut. |
| 3 | La gamme « restaurant » a sa propre classification, mais aucun nom de la liste ne la déclenche — la branche est morte. | Les deux jeux sont rendus, le contrôleur montre celui de la gamme. La règle reste vraie si les noms changent. |
| 4 | Le bouton principal s'appelle « Enregistrer et suivante » mais referme la modale au lieu de passer à la fiche suivante. | Reproduit tel quel — à confirmer par le design. |
| 5 | Quatre teintes du handoff n'ont pas de jeton : deux surfaces presque blanches, un vert et un or « texte sur fond pâle ». | Ajoutées en `--mdm-surface-pied`, `--mdm-surface-ligne`, `--mdm-vert-texte`, `--mdm-or-texte`. Les jetons `--secondary-vert` et `--secondary-premium` ne passent pas en 11 px sur leur propre teinte pâle. |
| 6 | La recherche du panneau et la saisie d'adresse sont des libellés statiques. | Reproduits tels quels : la modale n'a pas de formulaire, l'écran est une intégration. |

Les glyphes de la modale — `caret`, `caretleft`, `caretright`, `warn`,
`search`, `spinner`, `arrowright` — sont **extraits** de `mdm-icons.js`, pas
recopiés, par le même procédé que le jeu de navigation : le fichier du handoff
est évalué dans un bac à sable et les tracés sont écrits tels quels dans
`templates/mdm/_icones_modale.html.twig`.

Le script (`gen-icones-modale.js`) n'est pas versionné : le dépôt n'a pas de
dossier d'outillage et en créer un se décide à plusieurs. Il est à disposition
si vous le voulez dans le dépôt.

`mappin`, `pencil` et `lock` étaient déjà là ; la macro `icone()` route chaque
nom vers le bon jeu.

## Adaptation aux petits écrans

Le handoff ne fournit qu'un cadre de 1920 × 1080. Trois paliers ont été ajoutés
(colonne latérale rétrécie à 1600 px, passage en une colonne à 1366 px, grille
de compteurs empilée à 900 px). **Proposition d'intégration, pas une reprise de
maquette** — à valider par le design.

La modale d'édition rapide passe son corps en une colonne sous 1100 px.

---

## Tunnel de création d'une fiche

`/referentiel/fiche/nouvelle` · gabarit `mdm/creation_fiche.html.twig` ·
`assets/styles/creation.css` · `creation_controller.js` ·
`CreationFicheMaquette`

Maquette « Creation fiche », un fichier séparé du handoff principal. Le bouton
**« + Nouvelle fiche »** des deux listes y mène.

**Ce n'est pas un assistant pas à pas** malgré son nom : les sept blocs sont sur
une seule page, et c'est le premier — la gamme — qui débloque les six autres.
Le rail de gauche sert d'ancres et de tableau de bord du remplissage.

### Les sept blocs

| # | Bloc | Ce qu'il porte |
|---|---|---|
| 1 | Gamme | cinq cartes ; le choix fixe la structure de la fiche |
| 2 | Identité et localisation | nom, référence, adresse **ou** zone d'intervention |
| 3 | Classification | puces par axe, une à deux listes selon la gamme |
| 4 | Statut et référencement | actif, adhérent Business Premium |
| 5 | Visibilité | les 30 sites de diffusion, par groupe |
| 6 | Contact prestataire | quatre champs, ou le contact de repli de l'agence |
| 7 | Accès extranet | envoyer les identifiants, ou pas, et la trame d'email |

Le badge de chaque entrée du rail suit la règle de la maquette : `!` si le bloc
est fautif, `OK` s'il est renseigné, `—` s'il attend la gamme, `à faire` sinon —
et le remplissage l'emporte sur le verrou, d'où les trois `OK` déjà présents sur
la page vierge.

### Les six états

Servis par `?etat=`, comme les sections de l'éditeur de fiche. Par défaut
`vierge`, qui est l'état réel d'un écran de création.

| `?etat=` | Ce qu'il montre |
|---|---|
| `vierge` | rien n'est choisi, six blocs verrouillés à 72 % d'opacité |
| `lieu` | gamme Lieux, page complétée |
| `activite` | gamme Activités : la zone d'intervention remplace l'adresse |
| `repli` | contact de repli de l'agence, envoi des accès désactivé |
| `erreurs` | tentative de création : bannière, trois champs signalés |
| `encours` | voile de création, trois étapes, corps figé |

`?gamme=` force la gamme sans changer d'état — c'est ce que fait le clic sur une
carte. Le choix passe donc par le serveur : c'est lui qui décide des blocs, des
axes de classification, du nom pré-rempli et de la trame. Une seule source de
vérité, pas de dérivation dupliquée en JavaScript.

Tout le reste se règle sans rechargement : puces de classement, départements,
sites de diffusion, interrupteurs, contact de repli, mode d'envoi, panneau de
suggestions d'adresse, et le rail qui suit le défilement.

### Deux règles métier que la maquette encode

- **Une gamme mobile peut n'avoir aucune adresse.** Activités et Prestataires de
  services proposent « implantation fixe » ou « zone d'intervention » ; la zone
  remplace alors l'adresse sur les canaux publics.
- **Le contact de repli interdit l'envoi des accès.** Cocher le repli verrouille
  les quatre champs *et* rend « envoyer les accès » indisponible : l'adresse de
  l'agence n'est pas celle du prestataire. Le basculement est reproduit dans le
  contrôleur, message compris.

### Adresse fixe ou zone d'intervention

C'est le seul endroit du tunnel où deux blocs se disputent la même place, et la
règle de la maquette se lit en trois lignes :

```
implant    = ?etat=activite ? "zone" : "fixe"
showZone   = gamme choisie ET gamme mobile ET implant vaut "zone"
showAddress = gamme choisie ET pas showZone
```

Conséquence à ne pas manquer : sur `?etat=activite&gamme=lieu`, `implant` vaut
« zone » mais la gamme n'est pas mobile — **c'est l'adresse qui s'affiche**. Le
bloc porte donc `data-implantation` sur *ce qui est affiché*, pas sur le choix
brut. Trois combinaisons ne montraient ni l'un ni l'autre avant ce correctif.

Les deux blocs sont rendus ensemble sur une gamme mobile, et le bouton radio
permute sans recharger. La bannière d'erreurs suit : sans adresse affichée,
« Adresse postale » n'est plus un champ obligatoire manquant et le compte
redescend — `3 champs` devient `2 champs`.

La matrice complète — 6 états × 6 gammes, soit 36 combinaisons — est vérifiée au
navigateur sur quatre propriétés : gamme retenue, présence du choix
d'implantation, zone affichée, adresse affichée.

### Écarts assumés

| # | Constat dans la maquette | Décision d'intégration |
|---|---|---|
| 1 | Le fichier embarque son propre chrome de démonstration : colonne de 300 px pour changer d'état, contrôles de zoom, cadre de 1920 × 1080 mis à l'échelle. | Non intégré — c'est l'outillage du handoff, pas le produit. Les états passent par `?etat=`. |
| 2 | Le fichier redéfinit un en-tête de 64 px avec sept onglets, dont « Outils » que le reste du produit n'a pas. | L'en-tête réel du back-office est conservé. La simplification n'appartient qu'à ce fichier isolé. |
| 3 | Les champs sont des libellés statiques : aucun `input`, aucun formulaire. | Reproduits tels quels. L'écran est une intégration, rien n'est saisi ni envoyé. |
| 4 | La liste des sites de diffusion diffère de celle de la modale d'édition rapide : 30 sites au lieu de 31, « Hire Space » en moins. | Les deux jeux sont tenus **séparément**. Les fusionner masquerait un arbitrage que le design n'a pas rendu. |
| 5 | Le rouge d'erreur — `rgb(226,86,80)` sur `rgb(255,240,239)` — n'est ni `--secondary-rouge` ni celui de la modale d'édition rapide. | Ajouté en `--mdm-erreur-texte` / `--mdm-erreur-fond`. Troisième rouge du produit : à arbitrer par le design. |
| 6 | « Créer et enchaîner » et « Créer et enrichir » ne sont pas distingués dans le comportement. | Reproduits tels quels, tous deux inertes. |

### Deux ajouts au socle

- **`{% block main_attributs %}`** sur `.mdm-main` dans `mdm/base.html.twig` :
  le rail et le contenu sont frères, et le contrôleur du tunnel doit piloter les
  deux — le rail y déclenche les sauts vers les blocs.
- **La règle de base des liens du handoff**, `a { text-decoration: none }` avec
  survol vers le marine, présente dans le préambule de *chacun* de ses fichiers.
  Elle était redéclarée composant par composant ; les cartes de gamme et le fil
  d'Ariane, premiers vrais liens ajoutés depuis, arrivaient soulignés. Elle est
  désormais posée une fois, sous `.mdm-app`.

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
