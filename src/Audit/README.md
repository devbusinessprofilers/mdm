# Module Audit

Le module `Audit` conserve un historique append-only des modifications et
transitions des fiches, puis permet aux validateurs de restaurer certaines
valeurs passées. Aucune révision n'est jamais modifiée ni supprimée : une
restauration crée elle-même une nouvelle révision.

## Capture des changements

`DoctrineAuditSubscriber` écoute `onFlush` et enregistre insertions, mises à
jour, suppressions et changements de collections des entités auditées : la
`Fiche` et ses quatre déclinaisons (Lieu, Activité, Restaurant, Service
événementiel), leurs sous-entités (salles, périodes de fermeture, accès,
localisations, administratif, tarification, offres, ressources) et
`FicheAttributValeur`. Chaque entité est rattachée à sa `Fiche` racine.

Une révision (`audit_revision`) porte la fiche, l'action, la source (`pim`,
`external_api`, `worker`, `workflow`, `restoration`), l'acteur, ses rôles et
scopes, un identifiant de corrélation et la date. Les changements
(`audit_change`) stockent le chemin du champ (par exemple
`localisation.ville` ou `salles[ulid].label`) avec l'ancienne et la nouvelle
valeur en JSON, normalisées par `ValueNormalizer`.

Les champs techniques (`createdAt`, `updatedAt`, `version`, indicateurs de
complétude, empreintes calculées) sont ignorés. Les transitions de workflow
sont détectées et priorisées comme actions `submission`, `publication`,
`archive` ou `rejection`. `AuditContext` déduit la source et l'acteur de la
requête courante ; les contrôleurs peuvent forcer l'action via les attributs
`_audit_*`. La capture se désactive avec `AUDIT_ENABLED=0`.

## Consultation

Chaque domaine expose son historique en lecture sous
`/referentiel/{type}/fiche/{id}/historique`. `AuditHistoryFilterType` filtre
par action, champ, acteur et période ; la pagination est par curseur, 30
révisions par page.

## Restauration

Réservée à `ROLE_BP_VALIDATOR`, la restauration couvre en V1 les champs
scalaires, énumérations et dates des fiches et de leurs sous-entités
singulières. Les collections, les médias, le statut de workflow et les champs
disparus du modèle sont exclus.

- `GET /referentiel/historique/revisions/{id}/restaurer` : prévisualisation
  qui classe chaque changement en restaurable, identique ou exclu ;
- `POST` sur la même route : restaure tous les champs restaurables de la
  révision en un seul flush ;
- `POST /referentiel/historique/changes/{id}/restaurer` : restaure un champ
  unique.

`AuditRestorer` vérifie la version optimiste de la fiche
(`StaleVersionException` en cas de modification concurrente), applique les
valeurs via `RestorableFieldCatalog`, puis déclenche la replanification des
traductions et la réindexation comme toute mutation interne. La nouvelle
révision est tracée avec l'action `restore` et la source `restoration`.

## Composants principaux

- `EventSubscriber/DoctrineAuditSubscriber.php` : capture onFlush ;
- `Entity/AuditRevision.php` et `Entity/AuditChange.php` : état persistant ;
- `AuditContext.php` et `ValueNormalizer.php` : contexte et normalisation ;
- `Controller/*HistoryController.php` : historiques par domaine ;
- `Restore/AuditRestorer.php`, `Restore/RestorePreviewBuilder.php` et
  `Restore/RestorableFieldCatalog.php` : restauration ;
- `Controller/RestoreController.php` : routes de restauration.

Le module ne fournit aucune commande console : la capture est entièrement
événementielle.
