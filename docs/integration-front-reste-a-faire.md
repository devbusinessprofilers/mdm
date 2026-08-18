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
