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

## Socle : le design system du portail prestataire

Les treize écrans sont bâtis sur les **composants Twig du portail** repris de
`C:\wamp64\www
odevo` — 88 classes de composants, 72 gabarits, 21 énumérations,
162 modèles, 124 icônes, plus le thème Tailwind v4 (`assets/styles/app.css`).

**Il n'y a plus une seule classe CSS maison dans les gabarits.** Les sept
feuilles de style d'origine (5 277 lignes : `tokens`, `components`, `app-shell`,
`fiche`, `workspace`, `edition-rapide`, `auth`) ont été supprimées ; `app.js`
ne charge plus que `app.css`.

Un composant importé n'a été modifié qu'une fois : `Logo.html.twig`, dont le
lien et le fichier image pointent vers ce projet. La modification est signalée
en tête du fichier par un commentaire `ADAPTATION LOCALE`.

### Chaîne de compilation

Les assets ne sont **pas** servis dynamiquement dans cette installation : le
serveur lit `public/assets/`. Après toute modification d'un gabarit, d'un
contrôleur Stimulus ou d'une classe Tailwind :

```
php bin/console tailwind:build
rm -rf public/assets && php bin/console asset-map:compile
```

Sans le second couple de commandes, le navigateur continue de servir l'ancien
build — et une simple suppression de `public/assets` sans recompilation met
toutes les ressources en 404.

> **Piège** : Tailwind lit les gabarits en texte brut, **commentaires Twig
> compris**. Écrire un exemple de classe contenant un appel `url()` dans un
> commentaire fabrique une vraie règle CSS, et AssetMapper échoue ensuite à
> résoudre la cible — toutes les pages tombent en erreur 500.

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
  styles/app.css              thème du portail + Tailwind (feuille unique)
  images/auth/avatar-user.png photo de l'utilisateur identifié

templates/auth/
  base.html.twig              coquille commune (carte + visuel + panneau)
  connexion.html.twig  mot_de_passe.html.twig  mot_de_passe_defaut.html.twig
  mot_de_passe_oublie.html.twig  creation_mot_de_passe.html.twig

translations/messages.fr.yaml  libellés attendus par les composants importés

src/Account/Controller/AuthController.php   les 5 routes
```

`templates/auth/_composants.html.twig` (bouton, champ, tag, avatar, jauge) a été
supprimé : les composants du portail couvrent tout.

## Composants utilisés

| Élément de la maquette | Composant du portail |
|---|---|
| Bouton primaire / contour | `<twig:Button variant="primary\|outline" size="lg" full>` |
| Champ texte / email | `<twig:Form:TextInput>` |
| Champ mot de passe + œil | `<twig:Form:Password>` |
| Jauge de robustesse + 5 règles | `<twig:Form:Password withControl>` |
| Pastille cadenas, avatar | `<twig:Badge icon="lock">`, `<twig:Badge pictureUrl="…">` |
| Tous les textes | `<twig:Typography variant="…">` |

`withControl` est un gain net sur la maquette : celle-ci figeait la jauge à 49 %
avec trois règles vertes. Le composant les **calcule à la frappe** — vérifié :
`abc` → 20 %, `abcdefgh` → 40 %, `Abcdefgh1` → 80 %, `Abcdefgh1!` → 100 %, avec
les bonnes règles qui passent au vert.

Les cinq libellés de règles passent par `|trans` ; ils sont dans
`translations/messages.fr.yaml`. La locale par défaut est passée de `en` à `fr`.

## Écarts assumés

### Le bouton « Traduire » a été retiré

La maquette pose trois actions en tête de fiche : « Enrichir ce qui manque »,
« Extraire d'un document », « Traduire ». La troisième n'est plus rendue.

Décision de l'équipe back : la traduction est un traitement de fond, déclenché à
la validation de la fiche. Un bouton obligerait à y penser après chaque
modification, et des fiches passeraient à travers.

**Ne pas le « restaurer » en comparant à la maquette** — c'est un écart voulu.
 par rapport à la maquette

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

En attendant, le panneau de gauche retombe sur un dégradé de marque, posé en
Tailwind dans `auth/base.html.twig`.

**Pour brancher les vraies photos**, déposer les fichiers dans
`assets/images/auth/` puis remplacer le dégradé par un utilitaire d'image de
fond avec le cadrage de l'écran concerné. Le `base.html.twig` est partagé par
les cinq écrans : ajouter un bloc surchargeable si les cadrages diffèrent.

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
limité à 560 px. Les titres suivent la variante `heading-1` du portail, qui
bascule d'elle-même de 40/48 à 32/40 — exactement les deux paliers de la
maquette. **Ces règles sont une proposition d'intégration, pas une reprise de
maquette** : à valider par le design.

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
| **Tableau de bord** (accueil) | `accueil` | `/` |
| Mon espace de travail | `travail` | `/espace-de-travail` |
| Liste des fiches | `liste` | `/referentiel?etat=nominal` |
| Liste des fiches · Lieux | `liste` | `/referentiel/lieux` |
| Éditeur de fiche Lieu | `fiche` | `/referentiel/lieux/fiche?section=0..15` |
| Création d'une fiche | `creation` | `/referentiel/fiche/nouvelle?etat=vierge` |
| **Qualité** — Data Governance Workspace | `qualite` | `/qualite?onglet=miroir` |
| **Médias** — le DAM, 8 onglets | `medias` | `/medias?onglet=biblio` |
| **Outils** — journal des traitements | `outils` | `/outils` |

---

## « Tableau de bord » — écran d'accueil

**`/`** · gabarit `mdm/tableau_de_bord.html.twig` ·
`src/Pim/Maquette/TableauDeBordMaquette.php`.

Maquette `Tableau de bord.dc.html`. C'est la page d'accueil de l'application :
la racine la sert, et c'est le chemin qu'engendre
`path('app_mdm_tableau_de_bord')`. `/tableau-de-bord` reste servi par une route
d'alias, pour ne pas casser les liens déjà partagés.

Le `HomeController` du squelette et son gabarit vide `pim/home.html.twig` ont
été retirés : ils occupaient `/` et n'affichaient rien. Leur test
(`HomeControllerTest`, qui vérifiait que la racine rendait un corps vide) est
remplacé par `TableauDeBordControllerTest`.

« Mon espace de travail » reste accessible sur sa route et garde son entrée de
rail — rien n'a été supprimé de ce côté.

### Quatre zones, un rail qui les suit

| Zone | Contenu |
|---|---|
| 1 · À traiter | six files de travail |
| 2 · Santé du référentiel | indicateurs, champs faibles, croisement pays × typologie |
| 3 · Activité des équipes | activité par utilisateur, indicateurs, dernières publications |
| 4 · Médias et stockage | volume et consommation |

Le rail se met à jour au défilement — `assets/controllers/tableau_de_bord_controller.js`,
comportement repris de la maquette, y compris sa règle de fin de liste : arrivé
en bas, le repère suit la **dernière** zone au moins partiellement visible. Une
règle « plus grande surface visible » exclurait structurellement une zone finale
courte, et le clic qui amène jusqu'ici serait écrasé par le même gestionnaire.

### Les cinq états

Le sélecteur d'états de la maquette est l'habillage de son propre visualiseur :
il n'est pas intégré. Les états passent par la query string.

| État | URL |
|---|---|
| Vue nominale | `/` |
| Zone 1 vide | `/?etat=vide` |
| Volume d'alertes élevé | `?etat=fort` |
| Chargement progressif | `?etat=chargement` |
| Mode paramétrage | `?etat=param` |

Deux bascules supplémentaires : `?periode=7 jours|30 jours|Trimestre|Année` et
`?croisement=completude|publiees` pour le tableau croisé.

### Rien n'est recopié : tout est dérivé

Seules les constantes du handoff sont reprises. Sévérités, totaux, moyennes
pondérées sont **recalculés** en PHP comme dans la maquette, pour qu'un chiffre
de tête ne puisse pas contredire le tableau qu'il résume.

- La **sévérité d'une file** est un ratio à son volume normal, jamais un absolu :
  trois traitements en échec, c'est grave ; cent cinquante fiches en attente de
  publication, c'est un mardi. Seuils : ×1,5 attention, ×3 critique. Un
  traitement échoué est un état, pas un volume — toute occurrence est critique.
- La **complétude globale** est pondérée par le nombre de fiches, pas une
  moyenne de moyennes. L'indicateur, la barre, le badge du rail et le total du
  tableau la lisent tous.

Les chiffres ont été confrontés à ceux du handoff, rejoué en Node : complétude
73 %, 15 906 fiches publiées (84 %), files 126 / 0 / 453 selon l'état, et les
36 cellules du croisement. Tout concorde.

### Observation à remonter au design

En zone 3, la colonne de droite (indicateurs + dernières publications) est
nettement plus haute que le tableau d'activité, et la rangée est en
`align-items: stretch`. La carte de gauche s'étire donc et laisse ~300 px de
vide sous son tableau. C'est le comportement exact de la maquette — reproduit
tel quel — mais ça se voit.

## « Qualité » — Data Governance Workspace

`/qualite` · gabarit `mdm/qualite.html.twig` · `src/Pim/Maquette/QualiteMaquette.php`.

Maquette : `MDM prototype.dc.html`, page `qualite`.

Le MDM ne note pas la donnée, il mesure le **miroir** entre Salesforce, le MDM
et le portail BP. Une donnée est saine quand les trois portent la même valeur ;
le reste est une anomalie à trancher.

### Cinq onglets, une question chacun

| Onglet | URL | Question |
|---|---|---|
| Comparatif des 3 entités | `/qualite` | d'où vient l'écart |
| Conflits à arbitrer | `?onglet=conflits` | lequel arbitrer |
| Écarts de forme | `?onglet=formes` | lequel normaliser |
| Notifications | `?onglet=notifs` | qui a été prévenu |
| Décisions d'arbitrage | `?onglet=decisions` | qui a tranché |

Le rail porte les onglets et la règle « Qui fait foi », qui reste sous les yeux :
sans elle, les cinq onglets ne se lisent pas.

### Ce qui est dérivé

Score du miroir, parts des axes, totaux de pied, étiquette de conformité — tous
recalculés. Confrontés au handoff rejoué en Node : **89 %** (seuil 90, donc
« Sous le seuil »), 18 953 champs comparés, 16 842 concordants, axes à
89 / 6 / 3 / 1 %, pieds à 243 fiches et 2 583 champs normalisables.

### Un défaut du handoff, corrigé

« Anomalies par champ » rapporte chaque conflit au **total des champs comparés**
(84 / 18 953), ce qui affiche **« 0 % » sur les cinq lignes**. La part est
calculée ici sur le volume de conflits — 84 / 657 → 13 % — qui est le sujet de
la liste. Sans quoi la colonne ne dit rien. **À remonter au design.**

### Écarts assumés

- **Colonnes en fractions, pas en pixels.** Le handoff fixe des largeurs pour un
  cadre de 1920 avec un rail de 284 ; le rail fait 328 ici. Les cinq tableaux
  passent en grille à fractions égales.
- **Un seul châssis** sert les cinq onglets — en-tête, cartes, tableau, pied.
  Seule la matière change, ce qui évite cinq gabarits quasi identiques.
- Les déclinaisons **mobile et tablette** du prototype ne sont pas intégrées :
  le back-office est un poste de travail. Le handoff prévoit trois jeux de
  colonnes, seul celui du bureau est repris.

## « Extraire d'un document » — la modale de l'éditeur de fiche

`templates/mdm/fiche/_extraction.html.twig` ·
`src/Pim/Maquette/ExtractionMaquette.php` ·
`assets/controllers/extraction_controller.js`.

Maquette : `MDM prototype.dc.html`, état `extract` de la page `fiche`.

Trois temps, enchaînés sans rechargement — les trois volets sont dans le DOM,
le contrôleur Stimulus bascule :

| Temps | Contenu | Bouton |
|---|---|---|
| **Déposer** | zone de dépôt, 5 types acceptés | Lancer l'extraction |
| **Lecture** | le fichier, puis 4 passes (OCR, détection, rapprochement, confiance) | Voir les 9 valeurs lues |
| **Valider** | 9 valeurs, décision par ligne | Appliquer les valeurs acceptées |

Le bouton de gauche dit « Annuler » au premier temps et « Retour » ensuite ; au
dernier, « Appliquer » referme.

### Ce qui fait la valeur de cet écran

**La provenance affichée est la page du document, pas « l'IA ».** Une valeur
qu'on peut retrouver en page 4 se conteste ; une valeur attribuée à un modèle ne
se conteste pas. Chaque ligne porte donc sa page, sa confiance sur 4 barres, et
son verdict — complète la fiche, contredit la fiche, à relire, déjà identique.

L'acceptation en lot ne prend que les valeurs de **confiance maximale et pas
déjà identiques** : 4 lignes sur 9. Une valeur déjà portée par la fiche a son
bouton « Accepter » désactivé — il n'y a rien à accepter.

### Un défaut du handoff, corrigé

Le bandeau annonce « 9 valeurs lues · **4** champs vides, 1 contradiction, **3**
à relire, 1 déjà identique ». Ses propres données donnent **3** et **4**. Le
décompte est recalculé depuis les verdicts — sinon la ligne contredit le tableau
qu'elle résume. **À remonter au design.**

## « Médias » — le DAM

`/medias` · gabarit `mdm/medias.html.twig` · `src/Pim/Maquette/MediasMaquette.php`.

Maquette : `MDM prototype.dc.html`, page `dam`.

Un média n'est pas un fichier : c'est un **actif porteur d'un droit d'usage**,
décliné par canal et synchronisé avec le PIM.

### Huit onglets

| Onglet | URL | Ce qu'il traite |
|---|---|---|
| Bibliothèque | `/medias` | le stock, ses filtres, son téléchargement |
| Import & retouche | `?onglet=import` | dépôt et retouche non destructive |
| Reconnaissance IA | `?onglet=ia` | ce que l'IA déduit à l'import |
| Métadonnées & types | `?onglet=meta` | ce que le DAM sait faire de chaque type |
| Formats & CDN | `?onglet=formats` | les 5 déclinaisons et leur diffusion |
| Droits & consentement | `?onglet=droits` | qui autorise quoi, avec quelle preuve |
| Doublons | `?onglet=doublons` | groupes détectés par comparaison d'image |
| Synchronisation PIM | `?onglet=sync` | le miroir DAM ↔ PIM, dans les deux sens |

Le rail garde la **règle de diffusion** sous les yeux — un média sans droits
déclarés ne part sur aucun canal tiers, même si la fiche est publiée.

### Ce qui est dérivé

Stock total (142 806, somme des quatre régimes de droits), retouches du mois
(21 570), médias en double (9 sur 5 groupes — chaque groupe garde un
exemplaire), part diffusable (96 %), et les huit pieds de tableau. Confrontés au
handoff rejoué en Node, tout concorde.

La colonne « Télécharger » ne propose pas une action impossible : « Usage
interne » et « Sans droits déclarés » ne sortent pas du DAM, la cellule dit
pourquoi.

### Le DAM a quitté le rail des outils

Le prototype a été mis à jour entre-temps : **`dam` est devenu un onglet de la
barre de navigation**, avec ses huit onglets, là où il n'était qu'une entrée du
rail « Outils ». La barre porte donc de nouveau « Médias », et `/outils` ne sert
plus que le journal des traitements.

## « Outils » — journal des traitements

`/outils` et `/outils/medias` · gabarit `mdm/outils.html.twig` ·
`src/Pim/Maquette/OutilsMaquette.php`.

Maquette : `MDM prototype.dc.html`, pages `journal` / `outils` et `dam`.

Quatre outils partagent un rail, **un seul est intégré** :

| Outil | État |
|---|---|
| Mise à jour massive | écran d'attente |
| **Journal des traitements** | intégré · `/outils` |
| Campagnes IA | écran d'attente |
| Imports & exports | écran d'attente |

Le **journal** rassemble imports, exports, synchronisations, mises à jour
massives et campagnes IA — un seul endroit quand quelque chose s'est mal passé.
La colonne d'action change avec l'état : « Rejouer » sur un traitement échoué ou
terminé avec erreurs, « Détail » sinon.

### Ce qui est dérivé

Le pied de tableau : 3 traitements rejouables sur 8, volume cumulé 30 158. Le handoff ne les fournit pas — ils se déduisent des
lignes, donc ils ne peuvent pas les contredire.

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
  styles/app.css                 thème du portail + Tailwind (feuille unique)
  images/brand/bp-logo-couleur.png
  controllers/                   liste, creation, edition_rapide, collaborateurs
                                 + les 14 contrôleurs du portail, à plat
                                 (progress-bar, modal, select…)

templates/mdm/
  base.html.twig            coquille : header + rail + zone de contenu
  _composants.html.twig     ce qu'il reste : icônes héritées, menu déroulant,
                            barre de texte enrichi
  espace_travail.html.twig  liste_fiches.html.twig  creation_fiche.html.twig
  fiche_lieu.html.twig      _edition_rapide.html.twig
  fiche/                    7 partiels de sections

templates/pim/               72 gabarits de composants importés — NE PAS MODIFIER
src/Pim/Twig/Components/     88 classes de composants importées
src/Pim/Enum/ProviderPortal/ 21 énumérations importées
src/Pim/Maquette/            contenu de démonstration
```

Le rail est une macro locale de `base.html.twig` (`_self.groupe`,
`_self.entree`) — plus de dépendance à `_composants.html.twig` pour la coquille.

La **barre supérieure**, elle, est intégralement celle du portail.

**`FicheLieuDonnees.php` est généré. Ne pas l'éditer à la main.**

`EspaceTravailMaquette` porte les données des trois rôles telles que définies
dans la maquette. **C'est du contenu jetable** : il disparaîtra dès qu'un
service métier alimentera l'écran.

## La barre supérieure

Montée sur les composants du portail, sans une ligne de retouche :

| Composant | Rôle |
|---|---|
| `Logo` | lien vers l'accueil |
| `Header:Menu` | la navigation, avec `NavLink`, `DropdownNavLink` et `Sticker` |
| `Header:Profil` | photo et nom de l'utilisateur identifié |
| `Header:LanguageSwitcher` | drapeau et bascule de langue |
| `Header:Menu:BurgerMenu` | la même navigation sous 1536 px |

`Header:Menu` de nodevo construit son menu dans son constructeur, en citant des
routes qui n'existent pas ici. On ne l'a pas modifié : `items` et `user` sont
des propriétés publiques, on leur passe simplement le contenu du back-office —
`EnteteMaquette`, exposé aux gabarits par `EnteteExtension`.

« Référentiel » est rendu en menu déroulant, comme « Fiches » dans le portail :
ses six familles y sont, avec leurs glyphes.

### Trois aménagements, tous hors des composants

1. **Alias de routes** (`config/routes.yaml`). Les gabarits importés engendrent
   des URL par nom : `Header:Profil` vers `provider_portal_account_personal_information`,
   `Logo` vers `provider_portal_index`. Ces noms sont déclarés en alias vers les
   écrans MDM correspondants, plutôt que retouchés dans les composants.
2. **Avatar** déposé en `public/img/mock/avatar.png`, l'emplacement exact que
   cite `UserDTO::mock()`. Le profil du menu mobile construit son propre DTO,
   hors de portée d'un paramètre — servir le fichier là où il est attendu règle
   les deux cas d'un coup.
3. **Écran d'attente** (`/a-venir/{ecran}`). `NavLink` rend chaque feuille avec
   `path(route)` : une entrée sans route lève une erreur. Les six écrans pas
   encore intégrés pointent donc vers une page qui dit ce qu'il en est, au lieu
   d'un lien mort ou d'un menu amputé. **À supprimer au fil des intégrations.**

### Deux écarts assumés

- **Le champ de recherche du handoff MDM a été retiré.** L'en-tête du portail
  n'en a pas, et huit entrées plus la recherche débordent : les libellés se
  cassaient sur trois lignes. Le bloc est prêt à revenir.
- Les libellés du portail ne sont pas insécables ; à huit entrées ils se
  coupent. `[&_a]:whitespace-nowrap` est posé sur l'appel de `Header:Menu`,
  depuis l'extérieur.

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
son entrée** — dans le tableau `_self.groupe(...)` de `mdm/base.html.twig` pour
le rail, ou en troisième argument de `_self.lien(...)` dans
`espace_travail.html.twig`. Rien d'autre à toucher.

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
| 1 | L'en-tête affiche un logo de 200 × 40 que le plafond de lecture de 256 Ko rendait inaccessible. | Résolu : le logo couleur fourni est en place, servi par le composant `<twig:Logo>` du portail (`assets/images/brand/bp-logo-couleur.png`). |
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

La macro `menu_deroulant` de `mdm/_composants.html.twig` les rend en Tailwind :
enveloppe ancrée sous le champ, liste défilante, cases cochées pilotées par
`aria-selected` via la variante `group-aria-selected/option`.

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

---

# Vérification

Tous les écrans sont mesurés dans un navigateur réel (Selenium, 1920 × 1080)
après chaque campagne de modification. Dernier passage :

| Contrôle | Résultat |
|---|---|
| Écrans et états rendus sans exception | 19 / 19 |
| Classes CSS maison restantes dans le DOM | **0** |
| Erreurs console | aucune |
| PHPStan niveau 8 | aucune erreur |
| `lint:twig` | 95 gabarits valides |
| `phpunit` | 5 tests, 15 assertions |

Les états qui ne s'atteignent qu'à l'usage sont exercés par script plutôt que
constatés à l'œil :

- **Édition rapide** — les quatre états (adresse, erreur, enregistrement,
  panneau des sites) plus le recomptage : cocher deux sites passe le compteur à
  « 9 sur 31 », reconstruit les cinq puces de résumé, et le filtre « Retenus »
  ne laisse que 9 lignes sur 31.
- **Création du mot de passe** — la jauge suit la frappe (20 → 40 → 80 → 100 %)
  et les cinq règles passent au vert dans l'ordre attendu.
- **Matrice de capacités** — 16 colonnes, 5 salles, et **20 vraies cases à
  cocher** en mode édition.
- **Tableau de bord** — les cinq états rendent ; le rail suit le défilement
  (clic sur la zone 3 → `scrollTop 732`, repère sur la 3ᵉ entrée ; défilement en
  bas → repère sur la 4ᵉ) ; les chiffres dérivés concordent avec le handoff.
- **Texte enrichi** — TinyMCE 8.8.2 monte sur les deux champs riches, la barre
  rend les 10 boutons de la maquette **en français** (Annuler, Refaire, Gras,
  Italique, les quatre alignements, Liste à puces, Émojis), l'habillage du
  portail s'applique (cadre `rgb(51,160,190)`, rayon 16 px, en-tête
  `rgb(228,247,252)` de 36 px). L'éditeur est bien en écriture — `mode design`,
  `contentEditable true`, aucun bandeau — et une frappe au clavier se retrouve
  dans le `<textarea>`.

## Ce qui reste ouvert

| Point | Détail |
|---|---|
| `S4.MailInvitation` | toujours au-delà du plafond de lecture de 256 Ko |
| Photos des panneaux d'authentification | idem — dégradé de marque en attendant |
| Photo de salle (`assets/salle.jpg`) | idem — emplacement teinté au bon gabarit |
| Sites de diffusion (édition rapide) | seule liste restée en `<button role="checkbox">` : le filtrage et le verrouillage des sites obligatoires s'y appuient. Partout ailleurs — interrupteurs, cases de capacités, mots de passe — ce sont de vrais `<input>`. |

## Éditeur de texte enrichi

Les deux champs riches — « Paragraphe de description générale » (Description) et
« Descriptif des salles de séminaires » (Réunion) — utilisent
`<twig:Form:Wysiwyg>`, monté sur **TinyMCE 8.8.2**. La barre d'outils est
exactement celle de la maquette : annuler, rétablir, gras, italique, les quatre
alignements, la liste à puces, l'émoji. Elle est cette fois fonctionnelle, et la
saisie se recopie dans le `<textarea>` : les devs récupèrent le HTML au POST.

L'habillage vient de `assets/styles/tinymce/wysiwyg.css`, importé du portail —
cadre `primary`, coins à 16 px, en-tête `primary-4` de 36 px.

### Auto-hébergé, pas de clé d'API

Le portail charge TinyMCE depuis `cdn.tiny.cloud` avec une clé
(`%env(TINYMCE_API_KEY)%`). Ici il est **auto-hébergé** : pas de credential à
porter d'un projet à l'autre, pas d'appel tiers — la même question RGPD que
pour les polices Google se poserait sinon.

```
composer require tinymce/tinymce   # → vendor/, puis copie automatique
composer run tinymce:deploy        # recopie vendor/ → public/tinymce/
```

Le déploiement est rejoué par `post-install-cmd` et `post-update-cmd`, donc un
`composer install` sur un poste neuf suffit. `public/tinymce/` (12 Mo, 273
fichiers) est **généré** : ne rien y déposer à la main.

TinyMCE 8 auto-hébergé est sous GPL v2+ ; d'où `license_key: 'gpl'` dans les
options, sans quoi l'éditeur affiche un bandeau d'avertissement.

### Le contrôleur est celui du portail, à l'octet près

`assets/controllers/wysiwyg_controller.js` est une copie
conforme de `nodevo/assets/controllers/wysiwyg_controller.js` — `diff` ne
renvoie rien. Il tire `getDefaultLocale()` de `@symfony/ux-translator`, d'où
`composer require symfony/ux-translator:^2.36`, la même contrainte que chez eux.
`getDefaultLocale()` lit `<html lang>`, posé dans `templates/base.html.twig`.

Deux réglages que l'auto-hébergement impose sont donc portés **par le gabarit**,
pas par le contrôleur.

#### Déclaration de licence

Depuis TinyMCE 7, une instance auto-hébergée sans clé **se met en lecture seule**
et affiche un bandeau. Constaté : `mode readonly`, `contentEditable false`,
« The editor is disabled because a TinyMCE license key has not been provided ».
Le portail n'a pas le problème, il passe par le CDN avec sa clé d'API.

`mdm/base.html.twig` déclare donc l'usage sous GPL juste après le script :

```twig
<script>tinymce.overrideDefaults({ license_key: 'gpl' });</script>
```

`overrideDefaults` s'applique à toutes les instances sans que l'appelant ait à
le savoir — le contrôleur reste intact.

#### Catalogue de langue

Le paquet Composer n'embarque aucune traduction ; Tiny les distribue à part.
Elles sont versionnées dans `resources/tinymce-langs/` et recopiées vers
`public/tinymce/langs/` par le script de déploiement — sans quoi le prochain
`composer install` les effacerait.

⚠️ **Le contrôleur demande `language: 'fr-FR'` alors que TinyMCE nomme ses
paquets `fr_FR`.** La requête `langs/fr-FR.js` partait en 404 et l'interface
retombait en anglais — **c'est le cas sur le portail aussi**. Le dossier contient
donc les deux fichiers : `fr_FR.js` (le catalogue officiel) et `fr-FR.js`, le
même contenu réenregistré sous le code que réclame le contrôleur.

`resources/tinymce-langs/fr-FR.js` est à supprimer le jour où le contrôleur
demandera `fr_FR`. **C'est un correctif à remonter au portail.**

### Hauteur

`maxHeight` ne borne que la croissance automatique ; sans greffon `autoresize`
il ne réduit rien, et TinyMCE garde ses 500 px par défaut. La boîte est ramenée
à 200 px par `[&_.tox-tinymce]:h-[200px]!` sur l'enveloppe, dans
`fiche_lieu.html.twig` — depuis l'extérieur, là encore sans toucher au composant.

### Passer au CDN comme le portail

Si vous préférez aligner l'infrastructure sur nodevo plutôt que sur ce projet :
remplacer la balise `<script>` de `mdm/base.html.twig` par celle du portail,
retirer l'appel à `overrideDefaults`, câbler `TINYMCE_API_KEY`, et supprimer
`resources/tinymce-langs/` ainsi que le bloc « langues » du script de
déploiement. Le contrôleur, lui, ne bouge pas.

## Quatre défauts relevés dans le code importé

À remonter à l'équipe du portail, ils ne concernent pas ce projet :

1. Les gabarits de composants appelaient des identifiants Stimulus
   `provider-portal--*` alors que les contrôleurs du portail sont à plat —
   sans correspondance, barres de progression, modales et menus ne se lient
   à rien. Corrigé ici du côté des gabarits : le préfixe a été retiré des 146
   appels `stimulus_controller` / `stimulus_target` / `stimulus_action` et des
   attributs `data-*-target` de `templates/pim/`, et les contrôleurs sont
   posés à plat dans `assets/controllers/`. **C'est la seule entorse à la
   règle « ne pas modifier `templates/pim/` » : un réimport depuis le portail
   doit rejouer ce retrait de préfixe.**
2. `Badge` n'affiche jamais `pictureText` : il ne s'en sert que comme texte
   alternatif de l'image. Un badge sans image ni icône rend un rond vide.
3. Le contrôleur `wysiwyg` demande `language: 'fr-FR'` là où TinyMCE nomme ses
   catalogues `fr_FR`. La requête `langs/fr-FR.js` part en 404 et l'éditeur
   retombe en anglais — sur le portail comme ici. Un caractère à changer.
4. `Button::initIconColor()` et `initTextColor()` appellent
   `TypographyTextColorEnum::tryFrom($valeur)` sans écarter `null`, ce que PHP
   déprécie depuis 8.1. Chaque rendu de page en émet quelques-unes ; la suite de
   tests en relève six. `null !== $valeur ? tryFrom($valeur) : null` suffit.
   Rien n'a été touché ici : le code importé reste transplantable tel quel.
