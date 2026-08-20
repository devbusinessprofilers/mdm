# Sync marketplace — référentiel LOV (dictionnaire des listes de valeurs)

_État au 2026-08-12 — implémenté dans les deux repos (PIM + marketplace, branche
`feature/pim-sync`), validé bout en bout en local, non commité._

## Principe

Le PIM est la source de vérité du dictionnaire LOV (typologies, thématiques,
équipements, prestations…). Les payloads de fiches ne portent que des **codes**
(`GENERALE_TYPOLOGIE_3`…) ; la marketplace a donc besoin du dictionnaire pour
les résoudre. Décision : **miroir + projection**.

- Le PIM pousse le **snapshot complet** du dictionnaire (idempotent, garde de
  séquence ULID) à chaque mutation. Un renommage de libellé = un seul appel
  API, aucune resynchronisation de fiches.
- La marketplace **enregistre tout en miroir** (`bp_pim_attribute`,
  `bp_pim_attribute_value` — écriture seule, le front ne les lit pas) et
  **projette** les attributs qui ont un équivalent sur ses référentiels
  existants, que le front lit déjà (filtres, facettes, pages produit).

## Côté PIM (module Etl)

| Élément | Rôle |
|---|---|
| `MarketplaceLovPayloadBuilder` | Snapshot du dictionnaire effectif : 4 catalogues statiques fusionnés avec `pim_attribute_value` (renommages, positions, désactivations) + traductions de libellés `Available` |
| `SyncLovDictionary` (message, transport `marketplace`) | Vide : le snapshot est reconstruit à l'exécution |
| `SyncLovDictionaryHandler` | Génère la séquence ULID, `PUT /api/pim/referentiels` ; 409 = état plus récent (no-op), 4xx = échec définitif |
| `MarketplaceLovSyncScheduler` | Enfile le message via l'outbox (même transaction), gardé par `isConfigured()` |

**Déclencheurs** : `LovAdminManager::create()/update()` (admin
`/admin/listes-de-valeurs`), aboutissement d'une traduction de libellé
(`TranslateLovLabelHandler`), et `app:marketplace:sync --lov` (reprise
initiale / resync manuelle).

## Côté marketplace

- **Migration `Version20260812130000`** : tables miroir, garde de séquence
  globale `bp_pim_referentiel_state` (ligne unique), colonne `pim_code`
  (VARCHAR 96, unique, nullable) sur `bp_theme`, `bp_type_lieu`,
  `bp_equipement`, `bp_type_prestataire`.
- **`PUT /api/pim/referentiels`** → `PimReferentielUpsertService` :
  1. Garde de séquence (409 si périmée).
  2. Miroir intégral : upsert par code ; valeur absente du snapshot →
     `active=false`, **jamais de suppression**.
  3. Projection des attributs mappés — **mapping complet du 2026-08-12**,
     source cahier des charges (colonne « Table BDD » des classeurs
     `lamp-PIM/Cahier des charges/*.xlsx`) et bible des attributs : les
     anciennes listes marketplace étaient l'**union** des sous-listes PIM.

     | Attribut(s) PIM | Référentiel marketplace |
     |---|---|
     | `GENERALE_TYPOLOGIE` | `TypeLieu` |
     | `TA_THEMATIQUE` + `TA_CADRE_ENV` + `TA_AMBIANCE` | `Theme` |
     | `EQUIPEMENTS` + `SERVICES` + `TECHNIQUE_REUNION` + `INSTALLATION` + `BIEN_ETRE` | `Equipement` |
     | `THEMATIQUE_ACTIVITE` + les 9 attributs `TA_*_SS` (sous-thématiques par thématique) | `TypeActivite` (les deux niveaux cohabitent) |
     | `OBJECTIF_SEMINAIRE` | `Objectif` |
     | `LANGUE_PARLEE` | `Langue` (sans traductions Gedmo) |
     | `TYPE_PRESTATAIRE` + les 11 attributs `TS_*_SS` (sous-prestations par famille) | `TypePrestataire` (les deux niveaux cohabitent, comme la liste legacy) |

     Les autres sous-listes (RSE, chaînes hôtelières, jours d'ouverture,
     sous-thématiques d'activité, listes restaurant…) restent au miroir sans
     projection (pas de référentiel front correspondant).

     Résolution : lookup par `pim_code`, sinon **adoption par libellé
     normalisé** d'une ligne legacy (reprise au go-live, pas de doublon),
     sinon création. Dès qu'une ligne porte un `pim_code`, le PIM en est
     propriétaire : nom, traductions i18n Gedmo et `is_published` suivent le
     dictionnaire.
- **Relations produit ↔ référentiel** (`PimFicheUpsertService`). Règle des
  photos transposée : **aucune clé du groupe présente → on ne touche à
  rien ; sinon remplacement complet par l'union des clés présentes**. Code
  non résolu → ignoré + warning (pousser le dictionnaire puis resynchroniser
  la fiche).
  - Lieu : `typologie` → `Lieu.typesLieu` ; `thematiques ∪ cadres ∪
    ambiances` → `Produit.themes` ; `equipements ∪ services ∪
    techniqueReunion ∪ installations ∪ bienEtre` → `Produit.equipements`.
  - Activité : `objectifs` → `Activite.objectifs` ; `langues` →
    `Activite.langues` ; `sousThematiques* ∪ thematiques` →
    `Activite.typeActivite` (**première valeur résolue, la sous-thématique
    prime sur la thématique**, relation mono).
  - Prestataire : `sousPrestations ∪ prestations` → `Prestataire.typePrestataire`
    (**première valeur résolue, la sous-prestation prime sur la famille** —
    résout les prestataires détachés par la purge à la prochaine resync) ;
    `participants` → capacités min/max ; `couverture.departements` →
    `Prestataire.departements`.
  - La règle « première valeur » est transitoire : voir
    `docs/TODO-referentiels-multi-valeurs.md` (repo marketplace) pour le
    chantier ManyToMany.

## Mapping des champs hors LOV (2026-08-13)

La marketplace consomme désormais aussi, selon la règle « clé absente →
intouché » :

| Payload | Cible marketplace | Note |
|---|---|---|
| `typologie` (projetée) | `Lieu.hotelNbEtoiles` | « Hôtel N étoiles » → N, Palace → 5, sinon null |
| `tarifs.seminaire.journeeEtude` | `Lieu.prixJourneeEtude` | montant entier |
| `tarifs.seminaire.nuiteeResidentiel` (repli semi-résidentiel) | `Lieu.prixSeminaire` | montant entier |
| `youtube` (lieu + restaurant) | `Lieu.videoFr` | URL brute |
| `atouts` (lieu, restaurant, activité) | `Produit.atouts` (`bp_atout`) | réutilisation par nom, création sinon |
| `salles` (lieu + restaurant) | `Produit.salles` (`bp_salle`) | remplacement complet |
| `acces` (lieu) | `bp_distance_produit_transport` + `bp_moyen_transport` | gare→1, aéroport→2, métro→4 ; tramway/grandes villes/points d'intérêt ignorés ; distance en mètres |
| `loisirsInternes` (lieu) | `Produit.loisirs` (`bp_loisir`) | adoption par libellé normalisé uniquement (référentiel fermé, PIM en texte libre), aucune création |
| `participants` (service) | `Prestataire.capaciteMin/Max` | nouveaux champs PIM (CDC) |
| `couverture.departements` (activité + service) | `departements` | résolution par numéro puis par nom |
| `couverture` (activité) | `Activite.rayonActionFr` | « Toute la France » ou liste régions + départements |
| `telephone` (toutes gammes) | `Produit.telephone` | nouveau champ `pim_fiche.telephone`, colonne CSV « Téléphone » reprise à l'import ; espaces retirés, 14 car. max |
| `adresse.arrondissement` (toutes gammes) | `Produit.arrondissement` (`bp_arrondissement`) | valeur PIM, sinon déduit du code postal pour Paris/Lyon/Marseille (règle legacy) ; ligne créée au besoin |
| `prestataireEsat` / `demarcheRse` (service) | `Prestataire.categories` (`bp_categorie` : « ESAT / STPA », « RSE ») | reconstruit depuis les deux radios PIM |
| photos (sync + prune) | `Produit.nb_photos` | compteur legacy maintenu (il ne l'était plus — régression corrigée) |
| `partenaireBp` (toutes gammes) | `Produit.isPartenaire` | nouveau champ `pim_fiche.partenaire_bp`, colonne CSV « Tag » (vide → partenaire) reprise à l'import |
| `offreSpeciale` + `promotion.debut/fin` (lieu) | `Lieu.offreSpecialeFr` + `promotionStartDate/EndDate` | bloc « Tarifs & formules » PIM (`pim_lieu_tarification`, migration `Version20260813170000`), repris du CSV legacy ; le front n'affiche l'offre que pendant la période |

Corrigé au passage : `MoyenTransport::setVille()` assignait une propriété
inexistante `produit` (la ville n'était jamais enregistrée).

### Sous-listes par famille (implémentées le 2026-08-13)

**Chaque sous-liste est un champ à part** — comme thématique / cadre /
ambiance pour les lieux — et la marketplace **regroupe** leurs valeurs dans
la liste unique du front, comme le legacy :

- **Services** : 11 attributs `{FAMILLE}_SS` (65 valeurs CDC, ex.
  `TS_ANIMATION_ARTISTE_SS_10` = Photographe), seed
  `Version20260813100000`. L'import legacy pose famille **et**
  sous-prestation (Photographes → Animations & Artistes + Photographe…).
  Champs CDC ajoutés au passage : `participants_min/max`, `duree_minutes`.
- **Activités** : `SOUS_THEMATIQUE_ACTIVITE` éclaté en 9 attributs
  `{THEMATIQUE}_SS` (64 valeurs, codes inchangés) ; les bases déjà migrées
  sont remappées par `Version20260813110000` (définitions, identifiants
  stables et valeurs de fiches), `Version20260806180000` réécrite pour les
  installations neuves.

Dans les deux cas : un champ de formulaire par famille (cases filtrées par
le contrôleur Stimulus `sous-thematiques`, attaché via l'option `attr` du
formulaire), accesseur plat conservé en **union** pour la complétude,
l'import CSV (colonne unique, codes répartis par famille) et l'API portail
(contrat à plat, réparti par le PatchInput). Le payload fiche envoie une
clé par famille (`sousPrestationsAnimationsArtistes`,
`sousThematiquesSportivesLudiques`…) ; la marketplace fait l'union de ces
clés — même principe que thématiques + cadres + ambiances → thèmes.

## Runbook go-live (ordre impératif)

1. Migrations marketplace, déploiement des deux applis, force-recreate des
   workers PIM.
2. `app:marketplace:sync --lov` — le dictionnaire d'abord. Avec le mapping
   complet, l'adoption par libellé rattrapera la plupart des lignes legacy
   (« Piscine intérieure », « Wi-fi »…) **avant** la purge : leurs relations
   produit seront préservées (contrairement au local où la purge est passée
   avant, les valeurs y sont recréées vides).
3. Contrôle des adoptions, puis **purge du legacy** côté marketplace :
   `app:pim:purge-referentiels-legacy` (simulation par défaut, `--force`
   pour exécuter). Supprime toutes les lignes sans `pim_code`, leurs
   relations produit (FK CASCADE) et détache les prestataires de leur type
   legacy.
4. `app:marketplace:sync --all` — les fiches réappliquent leurs relations.

⚠️ La politique photos dépublie les fiches lieu sans 4 photos DAM traitées
(+ principale) : tant que le traitement de masse des ~167k photos n'est pas
fait, la plupart des fiches lieu partent en dépublication, pas en upsert —
leurs relations ne basculent donc pas.

Le canal marketplace n'a plus besoin d'action de masse : depuis le
2026-08-13, la sélection des sites de diffusion vient de la colonne
« Attribution visibilité » de l'export production, appliquée par
`app:legacy:import-collaborateurs` sur le référentiel métier réel seedé
par `app:sites-diffusion:sync` (38 sites, « Business Profilers » =
`marketplace_bp`, présélectionné pour les nouvelles fiches). Sur
`mdm_reel` : 26 612 fiches avec le canal marketplace, dont 20 127
publiées. Voir `docs/import-legacy.md`.

## Validation effectuée (local, 2026-08-12)

- Tests : PIM 510/510 + PHPStan propre ; marketplace 13/13 (services PIM),
  `lint:container` OK.
- Bout en bout, mapping complet : `--lov` → 40 attributs / 596 valeurs en
  miroir, **149 projections** (40 TypeLieu, 23 Theme, 52 Equipement,
  9 TypeActivite, 8 Objectif, 6 Langue — toutes adoptées sans doublon,
  11 TypePrestataire) ; fiche avec `typologie` → relation remplacée sur le
  front (« Hôtel (toutes catégories) » → « Hôtel 4 étoiles ») ; code inconnu
  ignoré + warning.
- Après les sous-listes par famille (2026-08-13) : 60 attributs au miroir,
  `bp_type_activite` = 73 lignes PIM (9 thématiques + 64 sous-thématiques),
  `bp_type_prestataire` = 76 (11 familles + 65 sous-prestations) ; remap
  local des 800 valeurs de fiches sous-thématiques sans reliquat.
- Bug préexistant corrigé au passage : l'entité `Langue` déclarait son
  repository sans l'importer (import orphelin `AtoutRepository`) → HTTP 500
  à la première résolution du repository.

## Purge du legacy (décision du 2026-08-12)

**Décision : les listes legacy de la marketplace sont supprimées, seules les
valeurs du dictionnaire PIM restent disponibles.** Pas de fusion manuelle :
les lignes que l'adoption par libellé n'a pas rattachées au dictionnaire
sont purgées par `app:pim:purge-referentiels-legacy --force`, avec leurs
relations produit (FK CASCADE : `bp_lieu_type_lieu`, `bp_produit_theme`,
`bp_produit_equipement`, tables i18n) ; les prestataires pointant un type
legacy sont détachés (`type_prestataire_id = NULL`, FK RESTRICT).

La purge couvre les sept référentiels projetés : types de lieu, thèmes,
équipements, types d'activité (activités détachées de leur type, FK
RESTRICT), objectifs, langues, types de prestataire.

Exécuté en local le 2026-08-12 : 48 lignes supprimées (10 types de lieu,
9 thèmes, 15 équipements, 14 types de prestataire), ~28 800 relations
produit purgées, 542 prestataires détachés ; puis, après le mapping
complet : 9 types d'activité + 10 objectifs legacy supprimés,
948 activités détachées (retypées à la resynchronisation).

Conséquences assumées :

- Les produits perdent ces classements jusqu'à la resynchronisation de leur
  fiche par le PIM (`--all`, après le traitement de masse des photos DAM).
- Les valeurs legacy qui manquent au dictionnaire (Île, Court de tennis,
  Parcours de golf…) ne réapparaîtront que si le métier les **ajoute au
  dictionnaire PIM** (`/admin/listes-de-valeurs`) — c'est désormais l'unique
  porte d'entrée.

## Valeurs sans équivalent — audit du 2026-08-12, arbitré avec le métier le 2026-08-17

Rien ne bloque techniquement : ajouter une valeur dans le catalogue PIM
suffit, la sync et la projection sont automatiques. Arbitrage rendu le
2026-08-17 : quatre valeurs **créées** dans le dictionnaire, le reste
**abandonné**.

### Valeurs créées le 2026-08-17 (PIM + projection marketplace)

Ajoutées aux catalogues statiques **et** au registre runtime
`pim_attribute_value` (migration `Version20260817100000`, identifiants
stables via `LieuLovCatalog::valueId` / `ActiviteLovCatalog::valueId`), puis
poussées vers la marketplace par `app:marketplace:sync --lov` (projection
`bp_type_lieu` / `bp_type_activite` par `pim_code`).

| Référentiel | Valeur créée | Code | Reprise legacy |
|---|---|---|---|
| Type de lieu | Lieu avec incentive intégré | `GENERALE_TYPOLOGIE_41` | 280 fiches (repli sur « Autres » supprimé) |
| Type de lieu | Résidence / Appart'hôtel | `GENERALE_TYPOLOGIE_42` | 203 fiches (repli sur « Appartement / Loft » supprimé) |
| Type de lieu | Salle / Bureau | `GENERALE_TYPOLOGIE_43` | 3 739 fiches (repli sur « Salle de réception » supprimé) |
| Thématique d'activité | Insolites | `TA_INSOLITE` | 490 fiches gamme activité (« Idée ») ; l'import legacy les mappait vers rien |

Les trois alias `TYPOLOGIE_ALIASES` correspondants ont été retirés du
`LegacyLovMapper` : le lookup par libellé du catalogue résout désormais ces
labels vers leurs codes dédiés.

### Valeurs abandonnées le 2026-08-17 (aucun code — déjà géré proprement)

| Référentiel | Abandonnée | Traitement en place |
|---|---|---|
| Type de lieu | Avec Hébergements | déjà consommé comme **flag `hebergement`** (bloc hébergement), jamais une typologie |
| Type de lieu | Restaurant | n'apparaît pas comme type de lieu dans le CSV (gamme de fiche à part) |
| Thème | **Île** | déjà dans `IGNORED_THEMES` du `LegacyLovMapper` |
| Équipement | **Court de tennis** · **Parcours de golf** | injection en loisirs internes **retirée** du `LegacyLieuRowMapper` (la marketplace les droppait déjà — référentiel loisirs fermé) |
| Objectif | **Animer** · **Intégration** | déjà repliés sur des objectifs existants (`OBJECTIF_SEMINAIRE_8` / `_1`) par le mapper activité |

Rappel Esat/RSE/Fibre/PMR (repris 2026-08-14) : Esat → typologie « Lieu
ESAT » (`GENERALE_TYPOLOGIE_11`), RSE → case `demarche_rse`, Fibre optique →
« Connexion internet filaire », Accès PMR → champ dédié `pmr` du lieu
(marketplace : `data.pmr`).

### Types de prestataire — résolu le 2026-08-13 (fausse alerte)

Photographes, Imprimeurs, Fleuristes, Constructions éphémères, Signalétiques
et Location de mobiliers ne sont **pas perdus** : le CDC les prévoit comme
**sous-prestations** des familles `TYPE_PRESTATAIRE` (`D - Services
Evénementiels - Champs.xlsx`, onglet « Typologie de prestataire »), et
l'import legacy (`LegacyServiceLovMapper`) reclasse déjà chaque prestataire
dans sa famille parente — aucune fiche n'a perdu son classement.

| Legacy purgé | Valeur CDC | Famille TYPE_PRESTATAIRE |
|---|---|---|
| Photographes | Photographe | Animations & Artistes |
| Imprimeurs | Imprimeur | Communication & Publicité |
| Signalétiques évén. | PLV / Signalétique | Communication & Publicité |
| Fleuristes / Décorations | Décoration florale | Technique & Audiovisuel |
| Constructions éphémères | Tentes & Chapiteaux | Technique & Audiovisuel |
| Location de mobiliers | Mobilier événementiel | Technique & Audiovisuel |

La granularité fine attend l'implémentation de la sous-liste de prestations
du CDC dans le PIM (à ce jour : ni catalogue statique, ni valeurs en base —
seules les 11 familles existent). Une fois créée, elle partira au miroir
marketplace automatiquement ; sa projection front resterait à décider.

### Écarts cahier des charges ↔ dictionnaire implémenté

L'onglet Typologies du CDC liste **Camping** (séparé — fusionné dans
« Village vacances / Camping » par la bible), **Cave**, **Moulin**, et le
libellé long « Châteaux / Domaines / Manoir / Abbaye » (bible : « Châteaux /
Domaines »). Si la bible fait foi, rien à faire ; sinon, valeurs à créer.

### Hors périmètre volontaire (pas des manques)

Listes restaurant, traiteur et plateaux-repas au miroir sans projection
(fiches restaurant servies comme des lieux, traiteurs sous Salesforce) ;
~30 attributs sans référentiel front (RSE détaillé, chaînes hôtelières,
modes de paiement…).

## Audit de couverture marketplace (2026-08-13)

Principe acté : peu importe que des champs MDM restent inutilisés ; en
revanche **tout champ lu par le front marketplace doit garder une source**
après l'arrêt de l'import CSV legacy. État par source :

- **PIM (sync)** : tout le contenu fiche — nom, description, téléphone,
  adresse + arrondissement, référentiels, capacités, tarifs de référence,
  atouts, salles, accès, loisirs, photos (+ compteur `nb_photos`),
  catégories ESAT/RSE, i18n.
- **Salesforce (conservé)** : les 6 notes RSE (`note*`) et tout le
  périmètre traiteurs plateaux-repas (`app:pr-import-produits`).
- **API Google (runtime)** : `google_place_id`, `google_note`,
  `google_review_count`.
- **Salesforce, bis** : `app:update-product-from-salesforce` couvre TOUS
  les produits publiés (match syspadId ↔ `Product2.ID__c`) et alimente
  aussi `evaluationClient`, `legalProceedings`, `restrictionRoles` — pas
  des orphelins ; `priceBookEntryId` par `updatePriceBookIdFromSF` (créa
  des lignes d'opportunité du tunnel événement). Voir « Reprise
  Salesforce → PIM » ci-dessous pour le devenir de ces commandes.
- **Supprimés le 2026-08-13 (décision : on ne garde rien)** :
  - jamais alimentés : `metadescFr`/`metakeyFr` (meta SEO), 
    `locationFichePdf`, `isSelectionHomepage` (slider homepage vidé),
    `videoEn`, `Produit.quartier` (FK morte), propriété fantôme
    `Prestataire.objectifs` — migration `Version20260813180000` ;
  - blocs éditoriaux CSV de la fiche lieu non repris : `restaurationFr` et
    `motExpert` (gabarits, ImportCommand et traductions Gedmo nettoyés) —
    migration `Version20260813190000`. L'offre spéciale et ses dates de
    promotion sont en revanche **reprises dans le PIM** (bloc « Tarifs &
    formules », voir tableau ci-dessus) ;
  - nettoyage complémentaire du 2026-08-13 : getters/setters fantômes
    `Prestataire.dureeMin/dureeMax` (aucune propriété ni colonne),
    colonnes `legal_proceedings` jamais alimentées des gammes `bp_lieu`,
    `bp_activite`, `bp_prestataire` (migration `Version20260813200000` —
    `bp_produit.legal_proceedings`, alimentée par Salesforce et lue par le
    front, est conservée), et attributs `#[Gedmo\Translatable]` orphelins
    laissés dans `Produit` par la purge metadesc/metakey (le slug héritait
    de deux Translatable → mapping Doctrine en erreur).
- **Partenaire BP (tranché le 2026-08-13)** : champ dédié
  `pim_fiche.partenaire_bp` (case « Partenaire BP » dans les 4 formulaires),
  repris de la colonne « Tag » du CSV à l'import (vide → partenaire, règle
  legacy) et consommé par la marketplace (`bp_produit.is_partenaire`).
  Volontairement distinct de `businessPremium`, qui pilote les relances de
  complétude.

## Reprise Salesforce → PIM (décisions du 2026-08-13, implémentée le jour même)

Le PIM devient le point de passage des données **fiche** issues de
Salesforce ; la plomberie **transactionnelle** reste sur la marketplace.

**Implémentation (non commité, les deux repos)** :

- **PIM** : `SalesforceApiClient` (OAuth JWT Bearer RS256 signé en openssl,
  SOQL paginée, env `SALESFORCE_LOGIN_URL/CLIENT_ID/USERNAME/PRIVATE_KEY` —
  vide = désactivé, voir `docs/SECRETS.md`) ; entité `FicheSalesforce`
  (`etl_fiche_salesforce`, migration `Version20260813210000`, une ligne par
  fiche connue de SF) ; `SalesforceFicheRefresher` (match
  `Fiche.code = intval(Product2.ID__c)`, garde-diff, `partenaireBp` écrasé
  via `preserveWorkflowDuring` — commission absente/non nulle → partenaire,
  règle legacy —, évaluation client 0.0 → null, fiches modifiées
  replanifiées par `MarketplaceSyncScheduler` dans la même transaction) ;
  commande `app:salesforce:refresh-fiches [--code]` ; cron quotidien 3h
  (`Schedule.php` → `RefreshFichesSalesforce` redispatché sur le worker
  etl) ; **webhook entrant** `POST /api/salesforce/produits` (2026-08-20,
  `SalesforceWebhookController`) : Salesforce notifie les codes produit
  modifiés (`{"codes": [123, "456"]}`, 200 max, jeton Bearer
  `SALESFORCE_WEBHOOK_TOKEN` — vide = 404, voir `docs/SECRETS.md`) et
  chaque code part en `RefreshFichesSalesforce(code)` sur la file etl
  (202) — les données ne sont jamais prises dans le payload, le refresher
  relit l'état chez Salesforce (mêmes règles que le cron, garde-diff,
  re-sync marketplace ; retries messenger en cas d'indisponibilité SF) ;
  payload : bloc `data.salesforce` `{rse{6 clés}, evaluationClient,
  procedureJudiciaire, contratsComptes[]}`, clé absente tant que la fiche
  n'a pas de ligne `etl_fiche_salesforce`. Les 4 formulaires affichent la
  case « Partenaire BP » désactivée avec l'aide « Géré par Salesforce. »
  dès que la fiche est connue de SF (éditable sinon). L'éditeur de fiche
  affiche un bloc lecture seule « Données Salesforce » (section
  « Visibilité & diffusion », bloc `salesforce` du catalogue de sections) :
  6 notes RSE, évaluation client, procédure judiciaire, contrats avec
  comptes et date du dernier rafraîchissement — ou un message d'absence si
  la fiche est inconnue de Salesforce.
- **Marketplace** : `PimFicheUpsertService::applySalesforce()` consomme le
  bloc (règle clé absente → intouché) → `note*`, `evaluationClient`
  (troncature int, comme l'ancienne commande), `legalProceedings`,
  `restrictionRoles` résolus depuis les contrats bruts via
  `UserService::getContractRoleMap()` (option `domaines_roles`).
- Tests : PIM 517 verts (dont `SalesforceFicheRefresherTest`, 4 tests
  database) + PHPStan propre ; marketplace 20 verts (dont bloc salesforce)
  + PHPStan propre sur le fichier modifié.
- **Refresh complet validé (sandbox, 2026-08-13)** : 23 607 produits
  Salesforce reçus, 22 619 fiches appariées, 11 807 modifiées, 988 sans
  fiche PIM. Récupérés : 524 notes RSE globales (dont détail complet sur
  certaines fiches), 1 227 évaluations client, 2 procédures judiciaires,
  2 jeux de contrats comptes (Edf/Engie/TotalEnergies/Covea), statut
  partenaire recalculé partout (22 369 partenaires / 7 424 non). ⚠️ En
  exécution **manuelle en local (env dev)**, lancer avec
  `php -d memory_limit=1G bin/console --no-debug app:salesforce:refresh-fiches` :
  le profiler Doctrine (backtrace par requête SQL) épuise sinon la
  mémoire sur ~23k produits. Le cron passe par le worker etl en
  `APP_DEBUG=0`, non concerné.
- **Validé sur fiches legacy réelles (sandbox SF, 2026-08-13)** : codes 1,
  2, 3, 5, 6, 1311, 1422 et 23503 appariés et rafraîchis (notes, éval,
  procédure, partenaire 0→1, statut `publiee` conservé). Bout en bout sur
  la fiche 23503 (seule publiée + diffusée marketplace + éligible photos
  en local) : PUT reçu, `bp_produit` mis à jour (éval 4→5, séquence
  avancée). Bug préexistant corrigé au passage :
  `replaceDistancesTransport` supprimait puis recréait les stations dans
  le même flush (INSERT avant DELETE chez Doctrine → violation de l'unique
  `produit_transport_station` à toute resynchronisation) ; réécrit en
  réutilisation par (type, nom) avec purge du reliquat et garde anti-
  doublon du payload.

**Retrait effectué sur `feature/pim-sync` (2026-08-13)** :
`app:update-product-from-salesforce` et `app:update-sf-ispartner`
supprimées de la marketplace, ainsi que leurs lignes de
`run-salesforce-refresh.sh` et les méthodes `SalesforceGlobalService`
devenues orphelines (`UPDATABLE_FIELDS`, `getProductsForUpdate`,
`getProductEntryIds`, `getRSEEntryIds`, `getReviewEntryId` —
`normalizeRecord`, `getPriceBookEntryIds` et `updateProviderMainPhoto`
conservées, encore utilisées). ⚠️ Conséquence pour le déploiement : dès
que cette branche part en prod, les notes RSE / évaluation / procédure /
partenaire ne sont plus rafraîchies **que** par le PIM — configurer les
secrets `SALESFORCE_*` du PIM et vérifier le premier
`app:salesforce:refresh-fiches` dans la même fenêtre de mise en prod.

| Commande marketplace | Décision |
|---|---|
| `app:update-product-from-salesforce` (6 notes RSE, évaluation client, procédure judiciaire, contrats comptes → rôles) | **Reprise dans le PIM** : le PIM interroge Salesforce (match `Fiche.code` = `Product2.ID__c`), stocke les valeurs sur la fiche (mise à jour technique, sans transition workflow) et les pousse via le payload de sync existant |
| `app:update-sf-ispartner` (`commission_standard__c` → `isPartenaire`) | **Supprimée à terme** : Salesforce est la source de vérité du statut partenaire ; le PIM alimente `partenaireBp` depuis `commission_standard__c` lors du même refresh, la sync existante (`partenaireBp` → `bp_produit.is_partenaire`) fait le reste. Résout la collision des deux écrivains sur `is_partenaire` |
| `app:import-sf-price` (`priceBookEntryId`) | **Reste sur la marketplace** : donnée du tunnel de commande (opportunités SF), une panne de sync PIM ne doit pas bloquer les ventes |
| `app:pr-import-produits` (traiteurs plateaux-repas) | **Reste sur la marketplace** pour le moment (les traiteurs sont hors PIM ; leurs notes RSE viennent déjà de `Product2Mapping`, aucun recouvrement avec la reprise ci-dessus — les produits traiteurs n'ont pas de `syspad_id`) |
| `app:update-sf-main-photo` (photo principale → SF, écriture) | **Reste sur la marketplace** ; à réexaminer quand le DAM sera la source des photos |

Point de design acté : la résolution `restrictionRoles` (contrats
Salesforce → rôles portail via l'option `domaines_roles`) est une notion
marketplace — le PIM transmettra les **valeurs de contrat brutes**, la
marketplace continue de les résoudre en rôles à la réception.

## Points ouverts

- Arbitrage des valeurs sans équivalent **clos le 2026-08-17** : 4 valeurs
  créées (incentive, résidence, salle/bureau, Insolites), le reste abandonné
  (voir la section dédiée). Types de prestataire résolus le 2026-08-13.
- Sous-prestations `TYPE_PRESTATAIRE` du CDC à implémenter dans le PIM
  (Photographe, Imprimeur, PLV / Signalétique…) pour retrouver la
  granularité des anciens types de prestataire.
- Types d'activité et de prestataire en multi-valeurs (règle transitoire
  « première valeur » en place) : chantier décrit dans
  `docs/TODO-referentiels-multi-valeurs.md` du repo marketplace.
- Maîtrise des traiteurs (PIM vs import Salesforce `app:pr-import-produits`).
- Reprise Salesforce → PIM implémentée, anciennes commandes marketplace
  supprimées (voir section dédiée) ; reste au go-live : configurer les
  secrets `SALESFORCE_*` du PIM et vérifier le premier
  `app:salesforce:refresh-fiches` dans la même fenêtre que le déploiement
  marketplace.
