# Bible « VERSION BP » — rapport d'application sur la gamme Lieu

Chantier du 2026-09-03 à partir de `VERSION BP - Bible.xlsx` (onglet Bible, colonnes
« Commentaires Clem » et « Taux de complétude »), limité à la gamme Lieu et à ce
qui concerne les données / le PIM. Les remarques de maquette, de front ou de
« rendu Nodevo » sont listées en fin de rapport, non appliquées.

## ⚠️ À lire d'abord : impact de la garde de publication

Les champs « Obligatoire » de la bible bloquent désormais l'envoi en validation et
la publication d'une fiche Lieu (décision Théofane). Constat sur `mdm_reel`
avant mise en place, sur les **11 948 lieux publiés** :

| Champ obligatoire vide | Lieux publiés concernés |
| --- | --- |
| Nombre de restaurants sur place | 11 948 (100 %) |
| Capacité maximale en configuration assise | 11 948 (100 %) |
| Bloc salles de réunion incomplet (case cochée) | 10 517 |
| Typologie absente | 575 |
| Bloc hébergement incomplet (case cochée) | 267 |
| Aucun accès gare | 22 |
| Aucun accès aéroport | 8 |
| Texte de description vide | 8 |

Ces fiches **restent publiées** (aucune dépublication rétroactive, aucune garde
ajoutée dans la réindexation) et l'import legacy continue de publier hors
workflow. Mais **toute fiche Lieu qui repassera par « Envoyer en validation »,
« Valider et publier », « Publier » ou « Republier » sera refusée** tant que ces
champs ne sont pas renseignés — en pratique, aujourd'hui, toutes. Les deux
champs restauration à 100 % de vide suggèrent qu'ils n'ont jamais été repris du
legacy : à trancher avec Clem (rattrapage de données, ou assouplissement de
l'obligation sur ces deux champs, ou valeur par défaut « 0 »).

Par ailleurs, les cases « dispose d'hébergement » et « dispose de salles de
réunion » sont **cochées par défaut** sur une fiche neuve : un lieu sans
hébergement doit décocher la case pour ne pas être bloqué par les champs
chambres.

## 1. Poids de complétude (appliqué)

Barème décidé : Obligatoire = **3.00**, « 2 points » = **2.00**, « 1 point » et
colonne vide = **1.00** (défaut inchangé). Migration `Version20260903090000`,
appliquée sur `mdm_reel` et `mdm_test`, puis
`app:completeness:sync-config --type=lieu` (révision 3, recalcul planifié sur
les ~19 800 lieux, workers recréés).

Résultat en base (`fiche_type = 'lieu'`) : 20 codes à 3.00, 60 codes à 2.00,
le reste à 1.00.

Accès désormais pondérés **par type** : nouveaux codes `ACCESS_AEROPORT` (3.00),
`ACCESS_GARE` (3.00), `ACCESS_METRO`, `ACCESS_TRAMWAY`, `ACCESS_GRANDE_VILLE`
(2.00), lus par de nouveaux lecteurs `accesAeroport()`… sur l'entité Lieu ; les
quatre anciens codes génériques (`ACCESS_NOM`, `ACCESS_DISTANCE_KILOMETRES`,
`ACCESS_DUREE_MINUTES`, `ACCESS_MODE_TRANSPORT`) et `SITE_PREMIUM` (LOV
supprimée en août) ont été désactivés par la synchro.

Correspondances de codes à connaître (la bible et le PIM ne nomment pas pareil) :
`DISPO_JOUR_OUVERTURE` → `JOURS_OUVERTURE`, `GENERALE_GAMME` / événements →
`EVENEMENTS_PREDILECTION`, `DISPO_HEURE_*` → `DISPO_HORAIRES_JOURS` (qui a enfin
un libellé « Horaires d'ouverture par jour » dans la config), `CONFIG_SUPERFICIE_*`
→ `CONFIG_CAPACITE_*`, `CONFIG_NOM_SALLE` → `CONFIG_NOM`, `CONFIG_DANSANT` →
`CONFIG_ESPACE_DANSANT`, `ATOUT_1..5` → `ATOUT1..5` (tous à 2.00, la bible ne
notant que le premier).

Champs de la bible **sans équivalent dans la complétude** (aucun code, pas de
poids possible) : `PJ_PLAN_GENERAL`, `PJ_SUPPORT_COMMERCIAUX`,
`CONFIG_PLAN_SALLE`, `CONFIG_PHOTO_SALLE`, `LOISIR_EXTERNE_PHOTO`, « Restaurant
associé », `INFO_LEGALE_ATTESTATION_VIGILANCE_URSSAF`.

## 2. Champs obligatoires bloquants (appliqué)

Nouveau service `src/Pim/Service/LieuObligationsPublication.php` : liste les
champs obligatoires vides d'un Lieu — typologie (≥ 1), texte de description,
≥ 1 accès aéroport, ≥ 1 accès gare, nombre de restaurants, capacité assise ;
si « dispose d'hébergement » : nombre total de chambres, capacité d'accueil,
texte de description ; si « dispose de salles » : nombre de salles, capacités
cocktail / théâtre max, théâtre min, surfaces min / max, texte de description.
Un texte enrichi ne contenant que des balises ou des espaces insécables compte
comme vide. Le nom et les photos restent couverts par la validation de
soumission et la garde photos existantes.

Points d'ancrage :
- **Envoyer en validation** (`ValidLieuValidator::submission`) : une violation
  par champ manquant, affichée en flash « chemin : « Libellé » est obligatoire
  pour la soumission ».
- **Valider et publier** : la fiche est validée mais non publiée ; le flash
  liste les champs manquants (et les photos si elles manquent aussi).
- **Publier** et **Republier** (routes unitaires, `FicheWorkflowManager`) :
  refus avec le message « Publication refusée. Champs obligatoires manquants :
  … ».
- **Actions de masse** Soumettre / Publier / Republier : la fiche Lieu
  incomplète est ignorée et comptée, comme pour les photos. (L'action de masse
  Soumettre ne passait par aucune validation jusqu'ici : elle filtre désormais
  ces champs, mais toujours pas les photos — comportement antérieur conservé.)
- **Non touché** : réindexation (`IndexFicheHandler`), `Fiche::publishForImport`
  et les commandes d'import legacy — le contournement demandé pour l'import
  legacy est le comportement existant.

## 3. Libellés de champs (appliqué)

Alignés sur la colonne « Libellé FR » dans `LieuFormCatalog` : « Groupe et
Chaîne hôtelière », « Cadre / Environnement » (typo « Environement » de la bible
corrigée), « Texte de description », bloc hébergement (« Mon établissement
dispose d'hébergement ? », « Dont nombre de chambres single / individuelle »,
« Dont nombre de chambres twin », « Dont nombre de chambres double »,
« Capacité d'accueil totale des personnes hébergées », « Texte de description
générale »), bloc salles (« Mon établissement dispose de salles de réunion ? »,
« Capacité de la plus grande salle en configuration cocktail / théâtre »,
« Capacité de la plus petite salle en configuration théâtre », « Surface de la
plus petite / grande salle de réunion (en m²) », « Texte de description »),
« Technique & Réunion », « Installation », « % de volume d'achat par catégorie
ESAT STPA », « Certification », « Capacité maximale en configuration debout /
assise », « Gestion de l'intervention d'un traiteur externe », « Traiteur
externe autorisé ? », « Type de cuisine ».

Effets induits : les libellés de la config complétude se sont rafraîchis à la
synchro ; l'export Excel et l'import en masse utilisent ces libellés comme
en-têtes de colonnes (un classeur exporté avant ce jour a les anciens
en-têtes, l'import propose « Vouliez-vous dire »). Le champ « Restaurant
associé » (liaison Lieu ↔ Restaurant) n'a pas de ligne dans la bible : libellé
conservé.

## 4. Tri alphabétique des LOV (appliqué)

Migration `Version20260903100000` : positions réécrites dans l'ordre
alphabétique des libellés (Collator fr, insensible à la casse) pour Typologie,
Groupe et Chaîne hôtelière, Événements de prédilection — sur les valeurs
actives en base, y compris celles créées en admin. Conséquence : « Autres Types
de Lieux » remonte à la 3ᵉ position (A…) au lieu de fermer la liste.

Effet de bord corrigé : la suggestion « Vouliez-vous dire » du rapport d'import
en masse retenait le *premier* des candidats à égale distance, donc dépendait
de l'ordre des LOV ; elle départage désormais par longueur puis alphabet
(`SuggestionProche`), indépendamment de l'ordre en base.

## 5. LOV « Événements de prédilection » (appliqué)

9 valeurs ajoutées (codes `_9` à `_17`) avec les libellés de la bible tels
quels : Lancement de véhicule, Road show, Convention, Comité de direction,
Afterwork, Colloque, Conférences et congrés, Salons et expositions, Simposium.
Données reportées puis anciennes valeurs désactivées : « Convention / Congrès »
→ « Convention », « Réunion / Comité de direction » → « Comité de direction »,
« Soirée / Réception » → « Afterwork ». Sur `mdm_reel` **aucune fiche ne
portait** ces valeurs (0 usage) : le remap est un no-op en données. Résultat :
**14 valeurs actives, exactement celles de l'onglet LOV de la bible** (les
5 conservées — Séminaire, Formation, Team building, Lancement de produit,
Événement hybride — plus les 9 ajoutées) ; les 3 anciennes restent en base,
inactives, pour l'historique.

Le dictionnaire LOV a été poussé vers la marketplace (`app:marketplace:sync
--lov`) ; les payloads de fiches déjà envoyés ne changent qu'au prochain push de
chaque fiche.

## 6. Capacité d'hébergement calculée par défaut (appliqué)

`LieuType` : à l'enregistrement, si « dispose d'hébergement » est coché, que la
capacité est vide et que le nombre total de chambres est renseigné, la capacité
est pré-remplie avec **nombre total de chambres + nombre de chambres twin**
(formule Clem, row 52). Une capacité saisie n'est jamais écrasée.

## 7. UI PIM (appliqué)

- **Info-bulle ERP** : aide « Établissement Recevant du Public » sous le champ.
- **Compteur de caractères** « x / 1000 » sous le texte de description
  (éditeur riche, contrôleur `wysiwyg`) et sous le texte de description de
  l'hébergement (zone de texte, contrôleur `compteur-caracteres`). Indicatif :
  passe en rouge au-delà, la limite serveur (1 000) reste la règle.
- **Copie des horaires** : sous les heures d'un jour ouvert, lien « Copier sur
  les jours suivants » (contrôleur `horaires-copie`, pur client). Les jours
  suivants déjà ouverts reçoivent les heures ; si aucun ne l'est, ils sont tous
  ouverts et remplis.
- Assets recompilés (`tailwind:build` puis `asset-map:compile`).

## 8. Commentaires ignorés (maquette / front / Nodevo)

- Row 29 : accès suggérés « comme Nodevo », triés du plus proche au plus loin,
  3 maximum, champs grisés (le PIM propose déjà le bouton « Suggérer les
  accès » ; la présentation relève du front).
- Row 35 : « mettre les blocs que portail Nodevo ».
- Row 46 : points d'intérêt en liste reliée à l'API (doublon de
  `DESC_GENERALE_POINT_INTERET`, row 34, dans la bible).
- Rows 47 / 55 : masquer ou griser les blocs hébergement / salles quand la case
  est décochée (mise en page).
- Rows 79–82, 90–91, 112, 159 : « reprendre le rendu Nodevo » ; row 112 ajoute
  « améliorer les zones d'import de fichiers ».
- Rows 95 / 97 / 106 : regrouper des champs sur une même ligne (mise en page).
- Rows 79–82 « liste des choix incomplète (voir LOV) » : vérifié, les LOV du
  PIM (Équipements 7, Services 7, Technique & Réunion 15, Installation 12,
  Bien-être 11) ont exactement les valeurs de l'onglet LOV — l'écart est dans
  l'affichage de la maquette, pas dans les données.
- Row 54 « il manque les équipements » : idem, 7 valeurs = 7 valeurs bible.

## 9. Questions ouvertes pour Clem / Théofane

1. **Champs restauration vides à 100 %** (voir encadré) : rattrapage, valeur par
   défaut ou obligation à revoir ?
2. Row 83 — RSE : PDF à intégrer (Nodevo) ou texte de description (PIM actuel) ?
3. Rows 40 / 53 — textes existants > 1 000 caractères : résumé par l'IA ? (le
   bouton « Suggérer » existe sur les descriptions ; un résumé automatique en
   masse serait un nouveau lot).
4. Row 90 — loisirs internes : la bible propose une LOV `LOISIR` de 34 valeurs,
   le PIM a un champ texte libre. À décider (créer la LOV + migrer le texte ?).
5. Champs sans complétude (§1) : faut-il les faire entrer dans le calcul (pièces
   jointes, plans / photos de salle, photo loisir, attestation URSSAF) ?
6. Typos de la bible reprises telles quelles : « Conférences et congrés »,
   « Simposium » (LOV événements) ; « Environement » corrigée d'initiative dans
   le libellé.
7. Cases « dispose d'hébergement » / « dispose de salles » cochées par défaut :
   à décocher par défaut maintenant que les blocs sont bloquants ?

## 10. Vérification

- Migrations jouées sur `mdm_reel` et `mdm_test` ; poids, lignes ACCESS_*,
  positions alphabétiques et désactivations contrôlés en base.
- `app:completeness:sync-config --type=lieu` : 0 créé, 5 désactivés, révision
  3. Le lot de recalcul en cours a été verrouillé par la recréation des workers
  (message livré non acquitté, redélivrance à 1 h) : recalcul relancé en
  révision 4 par `app:completeness:recalculate --type=lieu`, drain vérifié.
- PHPUnit complet : 956 tests, 953 OK, 3 échecs **préexistants** (2 dans
  `EnrichirFicheHandlerTest`, 1 dans `FicheExtractionEditeurTest` faute de
  paramètre `ocr.*` dans `mdm_test`). PHPStan : 0 erreur sur les fichiers du
  chantier.
- Nouveaux tests : `LieuObligationsPublicationTest` (unitaire), cas incomplet
  dans `FicheValiderPublierControllerTest`, fiche ignorée dans l'action de masse
  Soumettre (`ReferentielControllerTest`), 17 événements
  (`LieuLovCatalogTest`), accès par type (`CompletenessFieldCatalogTest`),
  capacité par défaut (`LieuTypeTest`), helper `tests/Support/LieuComplet.php`.
