# Plan d'intégration page par page — fidélité maquette

Branche de travail : `integration-front`. Maquette (lecture seule) : `git show origin/test-integration:<chemin>`.
Contrôle visuel : http://localhost:6080 (recompiler : `tailwind:build --minify` + purge `public/assets`).

## Constat de l'audit (2026-08-17)

- **Composants** `templates/pim/components/` : identiques à la maquette à 6 fichiers
  près (écarts mineurs voulus : Checkbox, ProgressBar, StateTag, Wysiwyg ; LiveMap et
  PaginatedCollaboratorList supprimés côté back). **Rien à reprendre ici.**
- **Coquilles** : `auth/base.html.twig` conforme ; `mdm/base.html.twig` adapté
  volontairement (rail déplacé en `{% block rail %}` par page, TinyMCE via contrôleur,
  recherche globale ajoutée). OK.
- **Le vrai écart est dans les pages** : elles ont été *réécrites* « au langage front »
  au lieu de reprendre les gabarits maquette. Exemples : tableau de bord 205 l. vs 563 l.
  maquette, médias 91 vs 284, création 136 vs 632, liste 345 vs 623 (+ modale édition
  rapide 435 l. absente). D'où le rendu éloigné de la maquette.

**Méthode** : pour chaque page, repartir du gabarit maquette (structure, classes, macros)
et le brancher sur les données/routes réelles — et non l'inverse.

**Règle « rien ne se perd »** : toute fonction maquette sans équivalent back est reprise
visuellement mais **désactivée** (`disabled` + tooltip « En développement »), jamais omise.
Réciproquement, aucune fonction back existante ne disparaît.

---

## ✅ Page 1 — `/connexion` + parcours mot de passe (fait, commit `2aba946`)

Réalisé le 2026-08-17 : parcours 2 étapes (`/connexion` → `/connexion/identifiant` POST
→ `/connexion/mot-de-passe`, hidden `email` vers le check `form_login`, `failure_path`
sur l'étape mot de passe), gabarits maquette sur les 6 écrans (connexion, mot de passe,
oublié, réinitialisation, changement, invitation), thème `_form-theme-auth`, jauge
`withControl` via `data-with-control`, flashes rendus dans `auth/base`, locale `fr` +
`translations/messages.fr.yaml`, gardes `tryFrom(null)` sur Tag/StateTag. Bouton support
désactivé + tooltip. Suite : 554 tests verts, 0 échec.

Détail d'origine (pour mémoire) :

Maquette : 5 écrans sur `auth/base` (conforme) — `connexion` (e-mail seul, « Identifiez-vous »,
bloc support), `mot_de_passe` (e-mail rappelé + « Utiliser un autre compte »), `mot_de_passe_defaut`
(variante badge sans avatar), `mot_de_passe_oublie`, `creation_mot_de_passe` (Password
`withControl` : jauge + 5 règles).
Actuel : `security/login.html.twig` en **1 étape** (email+password), déjà sur `auth/base`
mais titres/contenus différents ; `account/password/{forgot,reset,change}` et
`invitation/accept` encore sur l'ancien `base.html.twig` en HTML brut.

1. **Parcours 2 étapes sans toucher au firewall** : `/connexion` = écran e-mail
   (gabarit `connexion` repris tel quel), POST vers une petite route qui mémorise
   l'e-mail en session puis redirige vers `/connexion/mot-de-passe` = gabarit
   `mot_de_passe` (e-mail affiché, champ password + **input caché `email`**, POST vers
   le check `form_login` existant — pas d'authenticator custom). « Utiliser un autre
   compte » vide la session et revient à l'étape 1. Erreur d'identifiants → `Alert`
   sur l'écran mot de passe. `mot_de_passe_defaut` = même écran, badge `user`
   (le composant Badge gère déjà `pictureUrl`/`icon`).
2. **Bloc support** : destination non définie dans la maquette → bouton désactivé
   + tooltip (ou `mailto:` si décidé).
3. **`/mot-de-passe-oublie`** : porter `forgot.html.twig` sur `auth/base` avec le
   gabarit `mot_de_passe_oublie` (Badge `lock`, « Recevoir un lien »). `ForgotPasswordType`
   convient.
4. **Réinitialisation** : `reset.html.twig` → gabarit `creation_mot_de_passe`
   (`Form:Password withControl`, `password_controller.js` déjà présent), libellés maquette.
5. **Hors maquette, par cohérence** : `invitation/accept` (même flux que reset) et
   `password/change` habillés sur `auth/base`.
6. Tests : `SecurityControllerTest` (h1, parcours 2 étapes), tests forgot/reset/invitation.

## ✅ Page 2 — `/referentiel` (maquette `liste_fiches`) — fait (commits `37500ed`, `344e3f1`, `be3c97e`)

Réalisé le 2026-08-17 : gabarit maquette complet (rail maquette avec cases réelles
stylées `has-checked`, barre d'outils : recherche `f[q]` rattachée au formulaire du rail
par `form=`, picker de vues, Copier le lien réel via `liste#copierLien`, toggle densité
`?densite=compacte`, Colonnes désactivé), **bandeau 2 vues branché** (vueFiltres badges
réels / vueActions : compteur, « tout le filtre », boutons Valider·Publier·Archiver +
menu Plus d'actions avec tags Irréversible/« Plafond N » et gating client par
`data-plafond`, confirmation `confirm` sur les accès extranet, sous-champs contributeur
et sites dans le menu), tableau maquette en vrai `<table>` (colonnes ajoutées : Type =
1re typologie via `ReferentielRepository::TYPOLOGIE`, Pays, Actif = non-archivée,
Premium = business_premium ; marqueur IA fusée old-gold ; actions icônes), état vide et
pied maquette, modale « Gérer les vues » réelle. Sélection = vraies cases
`selection[ids][]` (theme checkbox relaie `attr`), case de tête JS (`aria-checked`
mixed). Chaque bouton d'action soumet `selection[action]` (plus de select/Appliquer).
Suite : 554 tests verts.

**Terminé le 2026-08-17 (commits `344e3f1`, `be3c97e`)** :
- **Bug racine réparé** : `@popperjs/core` manquait à l'importmap (import du
  `tooltip_controller` du portail) → TOUS les contrôleurs Stimulus étaient morts sur
  toutes les pages depuis l'import du design-system. Vérifié au navigateur headless.
- **Modale d'édition rapide** : coquille + turbo-frame `edition-rapide` qui charge la
  vraie page (restylée carte maquette : en-tête nom/gamme/statut/référence, nav
  précédente/suivante dans la frame, croix/Annuler → `_top`), deux boutons réels
  « Enregistrer » (recharge la fiche dans la frame) et « Enregistrer et passer à la
  suivante » ; sans JS le crayon ouvre la page plein écran. Écarts : pas de panneaux de
  suggestions d'adresse ni de jauge en tête, nom non éditable ici.
- Polish : loupe visible (fond sur l'enveloppe, pas sur l'input), cases « pavé »
  arrondies via `pim/form/_form-theme-referentiel.html.twig` (vrais inputs), résumé
  « N filtres actifs · N résultats » dans le rail, sélecteur de vues nommé par la vue
  courante (`vue_courante`). Tests : `EditionRapideModaleTest`, suite 556 verte.
- Retours 2026-08-17 (commit `6b37f26`) : LOV de la modale en **selects multiples**
  (composant Select — listes plates, il ne rend pas les groupes) ; fermeture de la
  modale sans navigation ni rechargement (croix/Annuler interceptés par
  `edition-rapide#fermer`, repli plein-page conservé) ; menu « Plus d'actions » :
  bloc « Attribuer » séparé (selects contributeur + sites, un bouton **Valider** →
  action `attribuer` qui applique l'un et/ou l'autre côté serveur) ; composant Select
  corrigé : option vide sur les selects simples non requis (sinon la 1re option était
  soumise par défaut — un contributeur pouvait être assigné à l'insu de l'utilisateur).
- `74ef1c6` : le thème doublait les crochets d'un ChoiceType multiple replié
  (`selection[sites][][]`) → la sélection de sites n'atteignait jamais le serveur.
  Vérification visuelle headless : `chromium --headless --screenshot` sur le dump
  authentifié servi depuis `public/` (bandeau, menu Attribuer, tout-sélectionner OK).
- `2dc7627` : Tag = div flex → l'envelopper d'un `inline-flex` dans les cellules sinon
  il s'étire (statut, BP) ; « En attente » repasse en neutre (absent de la maquette) ;
  colonne « Diffusion » en compteur `n / total` (total = sites actifs, `canaux_total`) ;
  en-têtes alignés maquette (Nom et ville, BP, Dernière modification) ; densité
  Normale/Compacte **sans rechargement** : `data-densite` sur la carte + variantes
  `group-data-[densite=compacte]/tableau:` et mémoire localStorage (`liste#basculerDensite`) ;
  bouton « Filtrer » épinglé sous la zone défilante du rail.

Détail d'origine (pour mémoire) :

Fait : rail repliable, badges de filtres actifs (2b856fb), picker de vues, pagination.
`liste_controller.js` identique à la maquette mais **le bandeau n'existe pas** (stubs
`plus`/`vues` hidden ; cibles `bandeau`/`vueFiltres`/`vueActions`/`compteSelection`/`ligne`/`caseTete`
absentes du template).

1. **Bandeau 2 vues** (maquette l. 184-273) : `vueFiltres` par défaut, `vueActions` dès
   qu'une ligne est cochée — boutons principaux (Valider, Publier, Archiver), menu
   « Plus d'actions » (Exporter CSV, Envoyer accès extranet, Assigner contributeur,
   Attribuer visibilité), Tags « Irréversible » (uniquement `acces`) et « Plafond N »
   (`ReferentielActionGroupee::PLAFONDS`), compteur « X sélectionnées sur Y ».
   Structure d'actions exposée par `ReferentielEcran` : `{ principales, secondaires }`,
   action = `{ code, label, icone, plafond, irreversible }`.
2. **Sélection** : cases « pavé » maquette sur les **vraies** cases `form_selection.ids`
   + case de tête, comptage du contrôleur branché sur les cases cochées ; soumission de
   l'action via champ caché/submit nommé, révélation des sous-champs `contributeur`/`sites`,
   gating au plafond, confirmation explicite pour `acces`.
3. **Colonnes manquantes** du tableau : Type/taxonomie (168px), Pays (82px), Actif
   (point vert/gris), Premium (« BP ») ; actions par ligne en icônes (crayon / flèche /
   points) au lieu de liens texte. Données toutes disponibles côté back.
4. **Barre d'outils** : recherche stylée (loupe, placeholder « nom · ville · identifiant »),
   toggle densité Normale/Compacte (routes + hauteurs 30/54px), « Copier le lien »,
   « Colonnes » désactivé (« à venir »).
5. **Édition rapide en modale** (gros morceau) : porter `_edition_rapide.html.twig`
   (435 l.) + `edition_rapide_controller.js` au-dessus de la liste (en-tête jauge +
   nav précédente/suivante, panneaux suggestions d'adresse / sites, confirmation de
   fermeture), branchée sur le formulaire réel de `app_mdm_edition_rapide` ; la page
   séparée reste en repli.
6. Tests : `ReferentielControllerTest` (391 l.) intégralement vert — la logique back
   ne bouge pas.

## ✅ Page 3 — Fiche éditeur (fait)

Fait le 2026-08-17 :
- **`adaf449`** — section « Services & équipements » (Lieu) en groupes de puces
  cochables maquette : LOV étendues (`LieuFormCatalog::choice(..., etendu:)`), bloc
  `puces` au catalogue, partial `mdm/fiche/_puces.html.twig`, widget case nue
  `data-puce` dans `_form-theme-fiche`.
- **`9160d85`** — refonte des onglets : TOUTES les sections rendues dans un seul
  formulaire (`FicheEditeurEcran` calcule tous les blocs, expose `sections`),
  volets basculés côté client (`fiche_onglets_controller`, aria-current + URL
  replaceState, liens `?section=N` en repli sans JS), blocs à formulaires propres
  dans des volets séparés HORS du formulaire principal, **barre épinglée
  `Form:FloatSubmit`** (bouton lié par `form=`) qui enregistre tout — flash
  « Fiche enregistrée. ». Pièges : `selectButton('Enregistrer')` attrape
  « Enregistrer la diffusion » (contains) → cibler `[data-float-submit-target="button"]` ;
  les assertions `table` doivent être scopées (plusieurs tables par page).

- **`c247930`** — `_disponibilites` (interrupteurs par jour sur les choix réels de
  DISPO_JOUR_OUVERTURE — SwitchButton gagne une prop `value` —, heures globales,
  écart assumé : pas d'horaires par jour dans le modèle) et `_formules` (« Booster
  ma visibilité », section maquette pure ajoutée au catalogue lieu, tout désactivé
  + infobulle ; le rail lieu passe à 17 sections — test ajusté).
- **`098c40b`** — `_capacites` : matrice des salles sur la vraie collection
  `SalleType` (nom + 9 capacités + 4 équipements en cases), en-têtes à icônes,
  ajout/retrait form-collection avec prototype au même gabarit, défilement
  horizontal. Écarts : pas de poignée/vignette/plan (pas de donnée), toujours
  en édition.

- **`691cb42`** — panneau latéral collaborateur : le volet Collaborateurs passe en
  deux colonnes, tableau + aside 420px `bg-primary-5` au gabarit maquette portant le
  **vrai formulaire d'invitation** (email = clé de rattachement, rôle, contact
  principal, accès). Le two-step factice de la maquette est abandonné (le vrai
  formulaire est complet d'emblée) ; l'édition des lignes reste inline. Test :
  « Inviter un collaborateur » → « Ajouter un collaborateur ».
- **`7664ce3`** — modale d'extraction : le dépôt OCR (et l'état « lecture en
  cours ») vit dans une `<twig:Modal identifier="extraction">` (composant portail —
  ATTENTION il est enregistré `Modal`, pas `Modal:Modal` ; le bouton est
  `Modal:TriggerButton`), ouverte depuis l'en-tête de fiche et depuis l'onglet
  Suggestions ; la revue des valeurs lues et l'historique restent dans l'onglet.
  Écart : pas de fil 3 étapes factice — le flux réel fait foi.

- **`6f58c02`** — `_medias` : le gestionnaire DAM réel garde toutes ses fonctions,
  habillé maquette — galerie de vignettes 152×104 (vraies photos, badge « Photo
  principale » sur la première, tuile « Ajouter un média » qui ouvre le sélecteur),
  en-têtes au design-system. Écart : pas d'onglets Photos/Plans/Supports (les deux
  sections réelles Photos/Documents restent empilées).

- **`3c40c3d`** (retour utilisateur) — plus de forêt de boutons dans Médias :
  **un clic sur une vignette ouvre les paramètres de la photo en modale** (métadonnées,
  recadrage, remplacer/relancer/supprimer, un seul « Enregistrer ») ; les vignettes
  restent réordonnables au glisser-déposer (elles SONT les items du contrôleur
  lieu-media) ; les documents passent en lignes compactes → modale par document ;
  libellés recentrés (« Enregistrer », « Déposer les documents »). Note : les
  métadonnées médias s'enregistrent par leur modale (endpoints AJAX dédiés) — les
  fondre dans l'Enregistrer global de la fiche serait un chantier back séparé.

- **`f2ffde5`** — documents en tuiles 152×104 comme les photos (icône PDF, titre,
  poids, clic → modale) ; composant Modal : défilement vertical seul à la barre
  fine `scrollbar`, `overflow-x-hidden` (bénéficie à toutes les modales).

La page 3 est close. Écart avec le plan d'origine ci-dessous : seuls
`_puces`, `_disponibilites`, `_capacites` et `_formules` existent en partials
(`templates/mdm/fiche/`) ; l'extraction OCR (modale) et les collaborateurs
vivent directement dans `templates/mdm/fiche_editeur.html.twig`, pas dans des
partials dédiés.

Détail d'origine (maquette `fiche_lieu`) :

Le squelette (rail groupé, en-tête, sections) est en place ; **aucun des 8 partials
maquette n'existe** (`templates/mdm/fiche/` absent). Contrôleurs Stimulus déjà là.

1. `_puces.html.twig` — groupes cochables + compteur « x sur n », sur les vraies
   collections de services/équipements.
2. `_disponibilites.html.twig` — privatisable, horaires par jour (switch + heures),
   périodes de fermeture (collections réelles).
3. `_capacites.html.twig` — grille des salles (surface, 8 configurations, équipements),
   édition vs lecture, sur la collection `salles`.
4. `_extraction.html.twig` — **modale** 3 étapes (dépôt PDF → validation IA → archivage)
   avec cibles d'`extraction_controller`, branchée sur le flux OCR réel (remplace la
   section inline actuelle).
5. `_collaborateurs.html.twig` + `_panneau_collaborateur.html.twig` — tableau + **aside
   latéral 420px** d'invitation/édition (cibles `collaborateurs_controller`), branchés
   sur `form_invitation`/affiliations (remplace le tableau + formulaire inline).
6. `_medias.html.twig` — onglets + galerie de vignettes, à réconcilier avec le gabarit
   DAM existant (`pim/lieu/_medias.html.twig`).
7. `_formules.html.twig` — purement maquette → rendu **désactivé + tooltip**.
8. Conserver : suggestions en attente, bloc Salesforce, historique (fonctions back sans
   équivalent maquette — rien ne se perd dans l'autre sens non plus).
9. Tests : `FicheLieuEditeurTest`, `FicheGammeEditeurTest`, `FicheExtractionEditeurTest`,
   `FicheCollaborateursEcranTest`.

## ✅ Page 4 — Création de fiche (fait, commit `ce20f9b`)

`ce20f9b` — voile de création : overlay maquette (spinner, carte centrée) posé à la
soumission par `fiche-creation#patienter`, le temps du POST synchrone. Écart : pas de
fil d'étapes factice. Le reste de la page était déjà conforme.

Détail d'origine :

Quasi conforme : mêmes 7 blocs conditionnés par la gamme, alerte doublons, contrôleur
Stimulus équivalent. Reste :
1. **Voile de création** (overlay : spinner, 4 étapes cochées, barre de progression) —
   à porter s'il manque à l'état `occupe`.
2. Vérifier pixel par pixel le rail à badges d'état (ok/erreur) et la barre d'actions
   fixe (Annuler | Brouillon | Créer & enchaîner | Créer).
3. Tests : `FicheCreationControllerIntegrationTest`.

## ✅ Page 5 — `/tableau-de-bord` (fait, commit `e2ad245`)

Gabarit maquette 4 zones + rail suiveur (`tableau_de_bord_controller`, inchangé) sur
données réelles : files en cartes colorées par sévérité (état « rien à traiter »
inclus), santé (indicateurs + delta complétude 30 j réel via `completudeDelta`,
champs faibles agrégés, croisement TRANSPOSÉ : lignes = gammes, colonnes = 6 pays,
paliers colorés en mode complétude, bascule réelle), activité (table + part de
l'activité + 4 KPI réels sans delta + dernières publications), médias (barre de
stockage segmentée — `DashboardPageProvider::storage()` expose segments/mediasTotal).
Conservés hors maquette : boutons admin, colonne Publiées, temps de validation,
compteurs semaine. Désactivés (maquette sans back) : filtres Pays/Typologie,
bloc Disposition. File « Accès » : pas de compteur back (5 files sur 6) — à trancher.
Piège : `loop` ne traverse pas le contenu d'un composant twig (copier dans une
variable). Tests adaptés (h1 = salutation, cartes au lieu du tableau).

Détail d'origine :

Actuel : 205 l. « au langage front » ; maquette : 563 l. en 4 zones + rail.
1. Reprendre le gabarit maquette : **rail des zones** (ancres 1-4) ; la section
   « Disposition/paramétrage » n'a pas de back → désactivée + tooltip.
2. Zone Files : ajouter la 6e file « Accès » (à exposer dans `TableauDeBordProvider`)
   ou la rendre désactivée.
3. Zone Santé : tableau croisé pays × typologies au format maquette (restructurer
   `countryRows` de `DashboardPageProvider`), bascule Complétude/Publiées.
4. Zone Activité : tableau par utilisateur + barre de part d'activité + 4 indicateurs
   équipe (delta) — agrégats à exposer, sinon désactivés.
5. Zone Médias : barre de stockage segmentée (images/docs/variantes) — `storage` existe.
6. Brancher `tableau_de_bord_controller.js` (scroll par zones).
7. Tests : `TableauDeBordControllerTest` (files « À traiter », publications).

## ✅ Page 6 — `/espace` (fait, commit `0688a42`)

Gabarit maquette : salutation h1, sélecteur de rôle désactivé (pas de back),
4 compteurs à pastille avec note + « Ouvrir » (assignées, à compléter < 50 %,
en attente, suggestions → Qualité/conflits), priorités en tableau maquette
(pastille de gamme teintée, badge de complétude urgent/proche/neutre — pas de
motif/échéance en donnée), raccourcis en pilules, activité au fil vertical.
Conservé hors maquette : « Mes vues enregistrées ». Test : h1 = Bonjour,
4 compteurs (ordre suivi).

Retours tableau de bord (`dce54a2`) : croisement remis dans le sens du back
(lignes = pays, colonnes = gammes) ; file « Suggestions à arbitrer » = OCR
+ BAN/Geoapify (URL → Qualité/conflits) ; cartes grisées « Demandes de
référencement » et « Accès à transmettre ».

Détail d'origine :

1. Entête maquette : salutation + **sélecteur de rôle** (Supply/Manager/Admin) — pas de
   concept back → désactivé + tooltip, ou perspective unique.
2. Compteurs : passer à la structure maquette (pastille couleur, note, lien « Ouvrir »)
   sur les 4 valeurs réelles de `EspaceTravailRepository::compteurs()`.
3. Priorités : colonnes maquette (Typologie teintée, Motif, Échéance badge urgent/proche) —
   Motif/Échéance n'existent pas en donnée → dériver (statut/complétude) ou dégradé assumé.
4. Activité récente : la maquette liste des événements (qui/quoi/quand), le back n'a que
   des agrégats → soit exposer les derniers événements d'audit, soit bloc désactivé.
5. Tests : `EspaceTravailControllerTest` — hooks `.card-grid`, `.card`, `.stat-value`.

## ✅ Pages 7-9 — Qualité, Médias, Outils (fait, commits `ca083e0`, `0a0d567`)

Châssis maquette sur les trois écrans, contenus réels inchangés :
- **Qualité** (`ca083e0`) : rail 5 onglets avec icônes + badges réels
  (`QualiteRepository::badges()` : conflits = OCR pending + ban_ecart, formes,
  notifs, décisions) + règle « Qui fait foi » persistante ; h1 = onglet actif.
  Écart : pas de bloc santé à score pondéré (aucun agrégat back) — la complétude
  par gamme/canal réelle tient ce rôle.
- **Médias** (`0a0d567`) : rail 8 onglets iconographiés + règle de diffusion
  persistante ; contenu supervision DAM inchangé.
- **Outils** (`0a0d567`) : rail = journal complet + familles (les destinations
  réelles sont les filtres du journal), encart Outbox dans le rail, « En échec
  seulement » et Importer dans l'en-tête. Pas de page `a_venir` créée : toutes
  les entrées pointent sur du réel.
Tests EcransPhase6 adaptés (h1 = onglet actif, libellé du rail dans le body).

Détail d'origine (Qualité) :

1. **Rail latéral** maquette avec onglets badgés + règle « Qui fait foi » persistante
   (actuellement onglets en boutons simples).
2. Macro `sante()` : score global %, axes pondérés, champs faibles — l'agrégat n'existe
   pas → soit le calculer (le tableau de complétude par gamme/canal existe déjà), soit
   bloc désactivé. Décision à prendre à l'attaque de la page.
3. Macros `tableau()`/`cellule()` (jeton, point, duo, lien, barre) pour tous les onglets
   (conflits IA, suggestions BAN, doublons, écarts de forme, notifications, décisions) —
   toutes les données existent (`QualiteRepository`).
4. Boutons d'en-tête maquette sans back → désactivés + tooltip ; conserver le lien
   « Configuration de la complétude » (admin).
5. Tests : `EcransPhase6Test`, `FicheAdresseSuggestionTest`, `VerifierAdresseEtrangereTest`.

Détail d'origine (Médias) — l'item 4 a été tranché autrement : pas de page
`a_venir`, les onglets sans gabarit (Import & retouche, Reconnaissance IA,
Métadonnées) sont grisés `disabled` avec badge « À venir » directement dans
`templates/dam/medias.html.twig` (commit `2e934f4`) :

1. **Rail latéral** + règle de diffusion persistante (« médias sans droits »).
2. **9 cartes d'anomalies affichées d'entrée** : les stats existent déjà dans
   `DamDashboardProvider::page()` → les brancher (aujourd'hui visibles seulement après
   navigation dans `_dashboard_contenu`).
3. 4 cartes KPI + bloc santé : pas d'agrégat back → désactivés + tooltip (même logique
   que Qualité).
4. ~~Onglets sans gabarit → renvoyer vers `a_venir` (page 9)~~ — remplacé par le
   grisage `disabled` ci-dessus.
5. Tests : `EcransPhase6Test`, `MediasSousOngletsTest`, `DamDashboardProviderTest`.

Détail d'origine (Outils) — les items 1-2 sont caducs : `a_venir` n'a pas été
créée (toutes les entrées du rail pointent sur du réel, cf. récap pages 7-9),
et OCR/traductions pointent vers leurs vraies pages réhabillées en page 10 :

1. ~~Créer `templates/mdm/a_venir.html.twig` + route `app_mdm_a_venir`~~ — abandonné.
2. Rail Outils maquette (Journal, Extraction OCR, Traductions, DAM) → réalisé avec les
   destinations réelles (commit `cd7d680`).
3. 4 cartes KPI (compteurs par famille — dérivables du journal) ; pied/pagination maquette.
4. Tests : `EcransPhase6Test`, `TraitementsEnEchecControllerTest`.

## ✅ Page 10 — Pages périphériques (fait, commit `d676f8b`)

Les 26 pages encore sur l'ancien `base.html.twig` passent par la coquille pont
`templates/mdm/simple.html.twig` (extends mdm/base, `{% block corps %}`) : une ligne
d'extends + renommage `body` → `corps` par page, AUCUN contenu touché. Leur
vocabulaire de classes hérité (page, card, badge, btn, table-wrapper, dam-stat…)
est traduit aux couleurs du PIM par la couche `.page-simple` en fin de
`assets/styles/app.css` (cartes blanches ombrées, marine, dégradé de marque sur
les .btn, têtes de tableau primary-5). Vérifié en capture sur /admin/listes-de-valeurs.
Test adapté : la recherche d'en-tête est la loupe du portail (plus de
`form.header-search`). Qualité (`e2c5b81`+retour) : « Recalculer les statistiques »
= vrai POST → messages ComputeDashboardStats/FieldFillRates ; « Arbitrer les
conflits » = lien vers l'onglet Conflits.

Détail d'origine :

Basculer sur `mdm/base` + composants, hooks de tests préservés : Recherche
(`pim/search/index`), OCR (`ocr/*`), Traductions (`enrichment/fiche_translations`),
Audit (`audit/*` 5 templates), Admin hub (`pim/admin`, `dashboard/index`), Paramètres
(`shared/parametre/*`), LOV (`pim/lov/*`), Sites de diffusion (`pim/site_diffusion/*`),
Complétude (`pim/completeness/*`), Import ETL (`etl/import/*`), Utilisateurs
(`account/admin/*`, `account/user_admin/*`), DAM détaillé (`dam/dashboard`).

## ✅ Reprise fidélité fiche Lieu — mise en page (2026-08-18, commits `6cb0bce`, `e24bb09`)

L'audit du 2026-08-18 a montré que la page 3 « faite » restait loin de la maquette
sur la mise en page. Repris sur `fiche_editeur.html.twig` (toutes gammes) :
- **Grille 2 colonnes** : champs simples en `w-[calc(50%-12px)]`, riches/groupes/
  collections en pleine largeur — au niveau des volets ET des sous-formulaires
  (block `form_rows` de `_form-theme-fiche`).
- **Cartes de section** `p-14 rounded-[32px]` avec compteur de champs terminaux
  (calculé dans `FicheEditeurEcran::nbChampsTerminaux`) et légende des 3
  pastilles d'autorité.
- **En-tête maquette** : h1 + tags, jauge sur la même rangée, boutons md à droite
  (« Enrichir ce qui manque » désactivé + tooltip — pas de back), page-description
  en `sr-only` (ancre de tests).
- **Barre d'actions basse** `h-24 bg-primary-4` : Annuler / « Enregistrer les
  modifications » (`form=form-fiche`, remplace FloatSubmit — tests recalés sur
  `button[form="form-fiche"]`) / workflow d'états en primary-3. Suppression et
  refus restent en haut.
- **Pied maquette persistant** : Suggestions en attente (libellé générique
  conservé, lignes maquette `bg-primary-5`, vraies forms Accepter/Ignorer,
  `data-suggestions-attente` déplacé), **Complétude par canal** branchée sur
  `completenessByChannel()` (4 canaux réels, pastilles seuils 80/40 — écart
  assumé : pas de déclinaison FR/EN au back), Historique en frise. Les blocs
  quittent leurs volets (filtre au rendu, catalogue inchangé — l'onglet
  « Suggestions IA & historique » ne garde que l'extraction OCR).
- **Rail maquette** : jamais de défilement (espacements `clamp`), titres de
  groupes noirs gras, icônes `w-5`, libellés `body`, pastilles `%` arrondies.
Suite : 557 tests verts. PHPStan : 1 erreur préexistante (l. 455, hors sujet).

Contrôle visuel headless (`03a1c6d`) — dump authentifié via test temporaire dans
`public/`, captures chromium sur les sections 0/1/4/10/12 :
- **Cases à cocher regroupées** : les runs consécutifs s'empilent par trois dans
  une demi-colonne, case + libellé sur la même ligne (`checkbox_row` du thème,
  partition `reduce` synchronisée entre `form_rows` et la boucle des volets).
- **Labels de groupe tus** au premier niveau (« Administratif * », « Restauration * »
  doublonnaient le titre de carte) — piège : `label: null` écrase le libellé
  configuré, ne passer `label: false` que dans la branche groupe.
- **En-tête** : le bloc d'actions se replie en 2 rangées (`max-w-[620px]`) au lieu
  de passer sous le titre.
- **Boutons d'action pleins** (Supprimer rouge, workflow marine) : le variant
  outline peint un dégradé `background-image` ET un `::before` blanc — il faut
  `bg-none!` + `before:hidden!` en plus de `bg-*!` sinon texte blanc sur blanc.

Retour utilisateur (`a476f13`) : « Informations générales » à l'ordre maquette
(nom, typologie, groupe/événements/ERP, site web — `LieuFormCatalog::general()`
réordonné, libellé « Nom du lieu », section 0 du catalogue réordonnée) ; les
cases à cocher passent toutes SOUS les autres champs (deux passes filter au lieu
de la partition par runs, volets + form_rows).

`02fee59` — matrice des salles : **régression latente réparée** — la branche
collection générique attrapait `salles` avant la branche `capacites`, le partial
`_capacites` n'était plus jamais rendu (les blocs spécialisés passent désormais
avant `prototype`). Habillage maquette complété : colonnes poignée + vignette
photo (inertes, « En développement »), lignes `h-[72px]`, « Ajouter une salle »
en pied, bandeau doré « Pré-remplir avec l'IA » branché sur la vraie modale
d'extraction OCR.

`2665620` — retours utilisateur et suite du plan :
- **Groupes de premier niveau aplatis** dans la grille de section (noms de
  soumission inchangés — l'API PATCH externe soumet sur la structure du
  formulaire, ne PAS déplacer les champs dans les types) : les cases des
  groupes rejoignent la pile de fin de section — ERP sous Téléphone. Les
  sections à bloc spécialisé (puces/dispo/capacités) gardent leurs groupes
  entiers, leurs partials attendent le widget complet.
- **Badges d'autorité supprimés** sur les champs (demande utilisateur) : la
  pastille + un `title` au survol suffisent, la légende de carte les décode.
- **Modale d'extraction** : fil 3 étapes branché sur l'état réel (dépôt /
  lecture / validation), zone de dépôt maquette (bord tillé, icône, badge PDF)
  autour du vrai formulaire, spinner animé sur l'état lecture. La revue reste
  dans l'onglet (décision antérieure conservée).
- Collections titrées (« Salles », « Périodes de fermeture », « Accès ») via
  un paramètre label du helper collection() de LieuType.

`b18fcf0` — chrome des champs : cadenas maquette dans la rangée de libellé des
champs `data-autorite="Salesforce"` (aujourd'hui partenaireBp piloté SF) ;
pilule « Suggérer » maquette sous les champs riches, désactivée + infobulle
(pas de back IA champ par champ — rien ne se perd), via un textarea_widget
propre à _form-theme-fiche pour ne pas toucher les autres écrans.

`84f68e0` — **réorganisation aux 16 onglets maquette** (audit onglet par onglet
demandé par l'utilisateur) : titres/ordre maquette (Localisation & accessibilité,
Thématiques & ambiances, Description, Réunion, Loisirs & team building, RSE,
Tarifs, Facturation & partenariat, Templates de message — maquette pure
désactivée), chemins pointés `groupe.champ` dans le catalogue de sections (le
groupe accessibiliteDescription se répartit sur 3 onglets SANS toucher la
structure de soumission — l'API PATCH externe en dépend), groupes Ma
fiche/Paramètres explicites. Onglets hors maquette reversés : disponibilités →
Info générales, visibilité + sites → Booster ma visibilité, Salesforce → RSE,
OCR → pied/modale. Piège : « Booster » précède désormais Collaborateurs, un
selectButton('Enregistrer') attrapait « Enregistrer la diffusion ».

`a8eaa8d` — retours : revue OCR rendue dans le bloc « Suggestions en attente »
du pied (form_revue en lignes maquette, plus d'onglet dédié), historique des
extractions dans la modale ; carte « Disponibilités » séparée de la carte des
champs (deux sections même data-volet — le contrôleur bascule tout).

`5d41e00` — **horaires par jour** (demande utilisateur, l'écart « assumé »
saute) : colonne JSON `dispo_horaires_jours` sur pim_lieu (migration
Version20260818120000), `HorairesJoursType` (TextType natifs `attr.type=time`,
« HH:MM » brut — le TimeType chaîne exige H:i:s ; le thème global honore
désormais attr.type), design maquette (colonne par jour, heures masquées si
fermé en pur CSS `group-has`), getter avec repli global décliné sur les jours
ouverts, dérivation de l'amplitude globale à la saisie (contrat marketplace
inchangé, `parJour` additif dans le payload), validation par jour, exclusion
import (pas de donnée legacy), workers force-recreatés.

`1e732a4` — passe typographie (retour utilisateur) : les libellés de champs
repassent en graisse normale (la maquette utilise la variante `input` sans
bold ; le `font-[900]` inliné venait du thème global — modifié dans le seul
`_form-theme-fiche`) ; les collections (« Périodes de fermeture », « Accès »)
gagnent un vrai titre `subtitle` gras avec « + Ajouter » à droite, sans
encadré ; « Sites de diffusion », « Données Salesforce » et « Valider les
valeurs lues » passent de heading-3/body-sm à `subtitle` gras (hiérarchie
maquette : heading-3 = titre de carte, subtitle = sous-bloc).

`d205810` — cases « porte d'entrée » (retour utilisateur) : « L'établissement
dispose d'hébergement » / « … de salles de réunion » ouvrent leur bloc en
pleine largeur sous le titre (liste `cases_en_tete` du gabarit) au lieu de
rejoindre la pile de cases en pied.

`94bace3` — sites de diffusion en **select multiple** (retour utilisateur, la
longue liste de cases est abandonnée) : choix aplatis (le composant Select ne
rend pas les optgroup — l'ordre suit déjà les groupes), mentions
(obligatoire)/(payant) conservées dans les libellés, label du champ tu (le
titre du bloc suffit). Les sites obligatoires restent réimposés par
soumettreSites(), quel que soit le widget.

Décision utilisateur : les champs maquette absents de la Bible (~35-40 sur
122 + modèles différents Tarifs/fiches liées) sont IGNORÉS — pas d'inputs
factices, pas d'ajout au modèle.

`7a02305` + `cbe5628` — onglet Collaborateurs refait au gabarit maquette :
grille `grid-cols-[84px_96px_84px_minmax(0,1fr)_116px_104px_78px_62px]`
(en-tête bg-primary-4, pastilles de droits vert coché / gris barré pour
Contact principal et Repli, nom en gras, crayon + corbeille en icônes) ;
panneau latéral à **deux états réels** : invitation par défaut, édition via
`?collaborateur=<id>` (le crayon recharge la page — pas de bascule JS
factice ; la ligne éditée est surlignée, Annuler revient à l'invitation).
Pièges : une classe `grid-cols-[…]` construite par concaténation Twig n'est
jamais compilée par Tailwind (l'écrire en toutes lettres) ; un `<button
type="submit">` brut viole la policy — passer par le composant Button réduit
à l'icône ; le premier `aside` du document est la navigation du portail
(cibler `aside[aria-label=…]` dans les tests).

`ebb448b` — périodes de fermeture au gabarit maquette : lignes nom (large) +
début/fin (dates natives) + corbeille alignée en pied, rendues dans la carte
Disponibilités par le partial (la collection quitte le macro générique) ;
prototype du form-collection au même gabarit.

Décision utilisateur : les onglets Médias restent en l'état (Photos/Documents
empilés) — l'écart avec les onglets maquette Photos/Plans/Supports est ignoré.

`5e03c1a` + `e293095` — barre d'actions compacte (h-16, trois boutons md) ;
**bug global réparé** : `@utility bg-border-hover-*` (et focus) ne consomme pas
`--value()`, Tailwind v4 ne générait donc JAMAIS la classe — au survol des
boutons outline, le voile blanc du ::before restait et le texte passait blanc
sur blanc, partout depuis l'import du design system. Utilities renommées en
dur (`bg-border-hover-primary-gradient`) dans app.css — correctif à remonter
au portail. « Supprimer » aligné md (BOUTON_SOBRE passe en data-size md).

La fidélité maquette de la fiche Lieu est COUVERTE : les seuls écarts restants
sont les décisions d'abandon explicites ci-dessus (champs hors Bible, onglets
Médias) et les fonctions maquette sans back rendues désactivées.

## Gammes Restaurant / Activité / Service alignées (2026-08-19, `10c1366`, `3f23c86`)

Les trois catalogues suivent l'organisation Lieu : titres/ordre calqués
(Localisation & accessibilité, Classification, Description, RSE — avec le bloc
Salesforce —, Tarifs, Booster ma visibilité — youtubeUrl + formules + sites —,
et en Paramètres Collaborateurs + Templates de message), groupes explicites.
Au passage : l'onglet « Suggestions IA & historique » restait déclaré dans les
trois gammes alors que ses blocs ne se rendent plus → **onglet mort supprimé**.
La carte annexe « Disponibilités » est généralisée : le Lieu passe par son
partial (compound + horaires par jour), le Restaurant rend ses champs à plat
(privatisations, heures TimeType, jours en interrupteurs horizontaux comme le
Lieu) + périodes de fermeture via le nouveau partial partagé
`_periodes_fermeture` ; les gammes sans dispo n'affichent pas la carte.
Tout le reste (grille, cases en pied, typo, barre, collaborateurs, pied,
modale) était déjà partagé par le gabarit unique.

`2adf11a` — retour utilisateur : les listes de cases de Service (prestations +
10 familles de sous-prestations) et d'Activité (types, thématiques,
sous-thématiques) passent en **selects multiples** (expanded → false). Le
contrôleur Stimulus `sous-thematiques` (filtrage des familles cochées) est
supprimé avec ses choice_attr — tous les selects de sous-familles restent
visibles, la famille figure dans le libellé.

`75b82e1` — retour utilisateur (onglet Tarifs) : bloc `money_widget` ajouté au
thème global → composant `Form:MoneyInput` du design-system, **icône € dans le
champ** (masque fr, milliers acceptés via grouping). Les décimaux de
MethodMappedFields (grille tarifaire Lieu) passent de NumberType à MoneyType
(`input: string` conservé — même famille de transformers, contrat API
inchangé) ; les MoneyType existants d'Activité/Service en profitent aussi.

`10e5655` — balayage onglet par onglet Restaurant + Activité (22 captures) :
- **Bug réparé** : une collection VIDE est un FormView Countable à zéro —
  falsy en Twig ET remplacée par le filtre `default()`. Le bloc « Périodes de
  fermeture » disparaissait donc tant qu'aucune période n'existait (impossible
  d'ajouter la première — Lieu et Restaurant). Détection par `reduce(...,
  null)` + `is not null`, jamais `first|default` ni truthiness sur un FormView.
- Libellés techniques nettoyés, format déplacé en `help` : « — un par ligne »
  (pays/régions/départements mobiles ×2 gammes, atouts, plus), « — texte
  simple V1 », « Tarif à partir de par personne » → « Tarif par personne (à
  partir de) » ; collection `offres` titrée.
- Constat conservé tel quel : uploads menus/supports Restaurant en champs file
  bruts (fonctionnels, chantier Dropzone séparé si souhaité).

`fc1000a` — onglet Médias des gammes clarifié (retour utilisateur : « des
champs pour ces mêmes images ? ») : les deux cartes coexistent par
construction — la carte de champs (form principal) est la voie d'AJOUT et
d'ÉDITION côté PIM (collection photos, dropzones menus/supports, titre/source),
la carte DAM en dessous est la CONSULTATION des mêmes médias (ses modales
photo gamme sont en lecture seule — la retouche vit sur le portail). Fusionner
exigerait de sortir ces champs du formulaire principal (forms imbriqués
interdits) → chantier AJAX à la Lieu, non engagé. Clarifié par les textes :
note du macro collection (rend `vars.help`), helps sur les collections photos
×3 gammes et sur titre/source, mention de la galerie corrigée (« déposées par
le prestataire via le portail ou ajoutées par la liste "Photos" ci-dessus »).

`9eca0b0` — **photos de gamme au modèle Lieu** (demande utilisateur) : zone de
dépôt AJAX, vignettes cliquables → modale de paramètres (catégorie, légende,
source, mots-clés, droits, recadrage/rotation, remplacer/relancer/supprimer),
réordonnancement au glisser, modales préchargées en arrière-plan. Portage par
généralisation : LieuPhotoManager/LieuMediaCsrfGuard/LieuImageUploader passent
en union de types (les gammes partagent RessourceLieu), `markChanged()` public
ajouté aux trois entités de gamme ; GammePhotoController (routes
`app_pim_gamme_photo_*` sur /referentiel/{gamme}/fiche/{id}/photos…) au format
actions-seules (le test d'architecture interdit constructeur/privées →
GammeEntiteResolver) ; LieuPhotoMetadataType gagne `avec_salles` (pas
d'association de salle hors Lieu, catégories salle retirées) ; le gabarit
`_medias_gamme` rend le gestionnaire lieu-media avec les URLs gamme +
`_medias_gamme_modales` ; la collection photos quitte le formulaire (champs
Médias = menus/supports/titre/source). Constructeur FicheEditeurEcran modifié
(CsrfTokenManager) → workers force-recreatés.

`79f9b34` — uploads habillés Dropzone (demande utilisateur) : opt-in
`attr: { 'data-dropzone': true }` dans le thème global (file → composant
Form:Dropzone, contraintes accept/max-files/max-size par attr), appliqué aux
menus + supports Restaurant et aux supports Activité/Service. Le contrôleur
`dropzone` du portail (récupéré de origin/front-end) était une ébauche —
réécrit : la sélection est rejouée via DataTransfer, la corbeille d'une carte
retire réellement le fichier de la soumission ; clés form.dropzone.* ajoutées
aux traductions. L'input natif couvre la zone : dépôt par glisser fonctionnel
même sans le contrôleur.

## Transverses

- `composants.html.twig` (vitrine des composants) : reprendre sous une route de dev
  (facultatif mais utile en QA visuelle).
- ~99 dépréciations PHP 8.5 `Enum::tryFrom(null)` dans les composants → garde `!== null`.
- Candidat Wysiwyg suivant : `comprendPrestation` (Activité), éventuellement `rseDescGenerale`.

---

## Méthode de vérification (chaque page)

```
docker compose exec -T php bin/console lint:twig templates
docker compose exec -T php bin/console cache:clear --env=test
docker compose exec -T php vendor/bin/phpunit <tests de la page>   # puis suite complète
docker compose exec -T php vendor/bin/phpunit tests/Shared/TemplateJavaScriptPolicyTest.php
docker compose exec -T php bin/console tailwind:build --minify
rm -rf html/public/assets    # purge → dev live recompile
```

Cible : rester à l'unique échec préexistant `ImportSchemaCoverageTest`. Policy : pas de
`<script>`/`<form>`/`<input>` bruts hors `templates/pim/components|form`, `templates/form`.
Comparaison visuelle systématique avec la maquette sur localhost:6080 avant de clore une page.
Commits : auteur Theofane seul, impératif anglais, jamais de `Co-Authored-By`.
