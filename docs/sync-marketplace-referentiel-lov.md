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
  3. Projection des attributs mappés :

     | Attribut PIM | Référentiel marketplace |
     |---|---|
     | `GENERALE_TYPOLOGIE` | `TypeLieu` |
     | `TA_THEMATIQUE` | `Theme` |
     | `EQUIPEMENTS` | `Equipement` |
     | `TYPE_PRESTATAIRE` | `TypePrestataire` (dictionnaire seul, relation non branchée) |

     Résolution : lookup par `pim_code`, sinon **adoption par libellé
     normalisé** d'une ligne legacy (reprise au go-live, pas de doublon),
     sinon création. Dès qu'une ligne porte un `pim_code`, le PIM en est
     propriétaire : nom, traductions i18n Gedmo et `is_published` suivent le
     dictionnaire.
- **Relations produit ↔ référentiel** (`PimFicheUpsertService`, fiches de
  type `lieu` uniquement) : `data.typologie` → `Lieu.typesLieu`,
  `data.thematiques` → `Produit.themes`, `data.equipements` →
  `Produit.equipements`. Règle des photos transposée : **clé absente → on ne
  touche à rien ; clé présente → remplacement complet**. Code non résolu →
  ignoré + warning (pousser le dictionnaire puis resynchroniser la fiche).

## Runbook go-live (ordre impératif)

1. Migrations marketplace, déploiement des deux applis, force-recreate des
   workers PIM.
2. `app:marketplace:sync --lov` — le dictionnaire d'abord (adoption des
   lignes legacy).
3. Contrôle des adoptions + arbitrage manuel des lignes sans équivalent
   (voir ci-dessous).
4. `app:marketplace:sync --all` — les fiches réappliquent leurs relations.

⚠️ La politique photos dépublie les fiches lieu sans 4 photos DAM traitées
(+ principale) : tant que le traitement de masse des ~167k photos n'est pas
fait, la plupart des fiches lieu partent en dépublication, pas en upsert —
leurs relations ne basculent donc pas.

## Validation effectuée (local, 2026-08-12)

- Tests : PIM 510/510 + PHPStan propre ; marketplace 11/11 (services PIM),
  `lint:container` OK.
- Bout en bout : `--lov` → 40 attributs / 596 valeurs en miroir, projections
  posées (40 TypeLieu, 8 Theme, 7 Equipement, 11 TypePrestataire) ; fiche
  avec `typologie` → relation remplacée sur le front (« Hôtel (toutes
  catégories) » → « Hôtel 4 étoiles ») ; code inconnu ignoré + warning.

## Arbitrages restants : lignes legacy sans équivalent PIM

48 lignes publiées restent sans `pim_code` (toujours visibles dans les
filtres). Deux voies de résorption : corriger le libellé côté PIM (adoption
au prochain `--lov`) ou poser le `pim_code` à la main sur la ligne legacy
(fusion), puis dépublier le reste.

### Types de lieu (10)

| Legacy | Équivalent PIM probable |
|---|---|
| Hôtel (toutes catégories) | Éclaté en Hôtel 2/3/4/5 étoiles + Palace — pas de 1:1 |
| Maison d'hôtel / Gîte / Ferme | « Maison d'**hôte** / Gîte / Ferme » (typo legacy) |
| Circuits Automobiles / Kartings | « Circuits Automobiles / Karting » (un « s » d'écart) |
| Centre de congrès | « Centre de congrès / Convention / Parc des expositions » |
| Village vacances | « Village vacances / Camping » |
| Résidence / Appart'hotel | « Appartement / Loft » ? à trancher |
| Salle / Bureau | « Salle sèche » ou « Salle de réception » ? à trancher |
| Avec Hébergements | Aucun (critère, pas une typologie) |
| Lieu avec incentive intégré | Aucun |
| Restaurant | Aucun (type de fiche à part côté PIM) |

### Thèmes (9)

Au vert, Campagne, Châteaux, Esat, Ile, Lac, Mer, Montagne, RSE.

→ Correspondent surtout à **`TA_CADRE_ENV`** du PIM (Mer, Au Vert, Campagne,
Montagne, Lac, Centre Ville), pas à `TA_THEMATIQUE` projeté. **Question
métier : projeter aussi `TA_CADRE_ENV` → Theme ?** (Châteaux relève de la
typologie ; Esat/RSE de l'impact social.)

### Équipements (15)

Wifi, Fibre Optique, Piscine Intérieure/Extérieure, Spa, Sauna, Hammam,
Jacuzzi, Fitness, Centre de remise en forme, Court de tennis, Parcours de
golf, Accès PMR, Climatisation en chambre / en salles de réunion.

→ Le `EQUIPEMENTS` du PIM est un équipement **de chambre** (TV, Balcon,
Bureau…), alors que cette liste legacy ressemble à **`BIEN_ETRE`** (Piscine,
Spa, Sauna, Fitness…) + `TECHNIQUE_REUNION` (Wi-Fi) + `INSTALLATION`.
**Question métier : la projection `EQUIPEMENTS` → Equipement est-elle la
bonne, ou faut-il plutôt projeter `BIEN_ETRE` (± `INSTALLATION`) ?**

### Types de prestataire (14)

| Legacy | Équivalent PIM probable |
|---|---|
| Animations Evènementielles | Animations & Artistes |
| Communications - Pub | Communication & Publicité |
| Goodies | Cadeaux clients & Goodies |
| Traductions - Interprètes de conférences | Traduction & Interprétariat |
| Transporteurs | Transports & Logistique |
| Techniques - Sonorisations | Technique & Audiovisuel / Son & Vidéo |
| Réalisations audiovisuelles - Vidéos - Visio | Technique & Audiovisuel / Son & Vidéo |
| Apps et sites web evenementiels | Digital & Hybride |
| Location de mobiliers / matériels | Divers & Sur-mesure ? |
| Photographes | Aucun |
| Imprimeurs | Aucun |
| Fleuristes / Décorations Evénementielles | Aucun |
| Constructions éphémères (chapiteau, stand...) | Aucun |
| Signalétiques événementielles | Aucun |

## Points ouverts

- Relation `TypePrestataire` non branchée : le PIM envoie `prestations[]`
  (multi), la marketplace a un `ManyToOne` (mono) — à trancher avec le métier.
- Attributs non projetés (BIEN_ETRE, SERVICES, INSTALLATION, RSE…) : au
  miroir seulement, en attente de validation métier.
- Arbitrage des 48 lignes legacy ci-dessus (un script SQL de fusion peut être
  préparé une fois les décisions prises).
- Maîtrise des traiteurs (PIM vs import Salesforce `app:pr-import-produits`).
