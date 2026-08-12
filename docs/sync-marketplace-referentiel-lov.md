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
     | `THEMATIQUE_ACTIVITE` | `TypeActivite` |
     | `OBJECTIF_SEMINAIRE` | `Objectif` |
     | `LANGUE_PARLEE` | `Langue` (sans traductions Gedmo) |
     | `TYPE_PRESTATAIRE` | `TypePrestataire` |

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
    `Activite.langues` ; `thematiques` → `Activite.typeActivite`
    (**première valeur = type principal**, relation mono).
  - Prestataire : `prestations` → `Prestataire.typePrestataire`
    (**première valeur**, mono — résout les prestataires détachés par la
    purge à la prochaine resync).
  - La règle « première valeur » est transitoire : voir
    `docs/TODO-referentiels-multi-valeurs.md` (repo marketplace) pour le
    chantier ManyToMany.

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

## Validation effectuée (local, 2026-08-12)

- Tests : PIM 510/510 + PHPStan propre ; marketplace 11/11 (services PIM),
  `lint:container` OK.
- Bout en bout : `--lov` → 40 attributs / 596 valeurs en miroir, projections
  posées (40 TypeLieu, 8 Theme, 7 Equipement, 11 TypePrestataire) ; fiche
  avec `typologie` → relation remplacée sur le front (« Hôtel (toutes
  catégories) » → « Hôtel 4 étoiles ») ; code inconnu ignoré + warning.

## Purge du legacy (décision du 2026-08-12)

**Décision : les listes legacy de la marketplace sont supprimées, seules les
valeurs du dictionnaire PIM restent disponibles.** Pas de fusion manuelle :
les lignes que l'adoption par libellé n'a pas rattachées au dictionnaire
sont purgées par `app:pim:purge-referentiels-legacy --force`, avec leurs
relations produit (FK CASCADE : `bp_lieu_type_lieu`, `bp_produit_theme`,
`bp_produit_equipement`, tables i18n) ; les prestataires pointant un type
legacy sont détachés (`type_prestataire_id = NULL`, FK RESTRICT).

Exécuté en local le 2026-08-12 : 48 lignes supprimées (10 types de lieu,
9 thèmes, 15 équipements, 14 types de prestataire), ~28 800 relations
produit purgées, 542 prestataires détachés.

Conséquences assumées :

- Les produits perdent ces classements jusqu'à la resynchronisation de leur
  fiche par le PIM (`--all`, après le traitement de masse des photos DAM).
- Les valeurs legacy qui manquent au dictionnaire (Photographes, Imprimeurs,
  Fleuristes, Constructions éphémères, Signalétiques…) ne réapparaîtront que
  si le métier les **ajoute au dictionnaire PIM** (`/admin/listes-de-valeurs`)
  — c'est désormais l'unique porte d'entrée.

## Points ouverts

- Types d'activité et de prestataire en multi-valeurs (règle transitoire
  « première valeur » en place) : chantier décrit dans
  `docs/TODO-referentiels-multi-valeurs.md` du repo marketplace.
- Maîtrise des traiteurs (PIM vs import Salesforce `app:pr-import-produits`).
