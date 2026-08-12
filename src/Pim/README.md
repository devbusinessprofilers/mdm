# Module PIM

Le module `Pim` est le cœur fonctionnel du MDM. Il porte les fiches prestataires,
leurs données structurées, leur workflow, leur complétude, la recherche,
l'administration des LOV et leur exposition HTTP.

## Domaines opérationnels

Quatre types disposent aujourd'hui d'un modèle, d'un CRUD d'administration,
d'une validation, d'une recherche et d'une API :

- Lieu ;
- Activité ;
- Restaurant ;
- Service événementiel.

`Traiteur` reste une valeur historique de l'enum `TypeFiche`, mais ne fait pas
partie des surfaces opérationnelles ni de la recherche globale actuelle.

Toutes les fiches partagent `Fiche` : ULID, type, code numérique global, libellé,
statut, version optimiste et dates de workflow. Le code est attribué par la base
au premier `INSERT`, reste unique et immuable, et n'est jamais réutilisé après
suppression.

## Workflow et droits

Le cycle nominal est :

```text
en_cours -> en_attente_validation -> validee -> publiee -> archivee
                    |-> rejet -> en_cours
```

La soumission exécute les groupes de validation Draft et Submission. La
validation, la publication, le rejet et l'archivage passent par
`FicheWorkflowManager` et sont contrôlés par le module `Account`. Une mutation PIM effectuée par un validateur
conserve le statut et toutes les métadonnées du workflow ; la même mutation par
un éditeur renvoie la fiche en cours. Une publication, ou la modification par
un validateur d'une fiche publiée, planifie les traductions lorsqu'une source
traduisible a changé.

## Administration et API

Les contrôleurs Symfony fournissent les listes, formulaires, vues détail,
actions de workflow, photos et documents. API Platform expose les collections,
fiches, médias et documents avec DTO, providers et processors dédiés. Les
mutations externes utilisent `ETag`/`If-Match` et préservent le statut du
workflow lorsqu'elles ne représentent pas une transition explicite.

Les documents sont privés par défaut et leur publication est une action
distincte. Le binaire et ses rendus relèvent du DAM ; le PIM conserve le lien,
l'usage, les droits, la légende, la position et l'état de publication.

## LOV

`AttributDefinition`, `ValeurAttribut` et `FicheAttributValeur` sont la source
runtime des listes de valeurs. `LovRuntimeCatalog` alimente formulaires,
validation, API et index sans recharger toute la base à chaque appel. Les codes
sont stables ; une valeur déjà utilisée est désactivée plutôt que supprimée.
L'administration permet de modifier position, activation, libellé et
traductions.

## Recherche

`FicheSearchIndexer` construit `pim_fiche_search` après les changements utiles.
`MariaDbSearchEngine` utilise la recherche FULLTEXT MariaDB, une priorité sur le
code exact, des filtres par type/statut et une pagination par curseur. La route
`/recherche` interroge simultanément les quatre domaines opérationnels.
`FicheDuplicateDetector` signale les fiches candidates au doublon.

## Diffusion, relances et collaborateurs

`FicheSiteDiffusion` relie chaque fiche à ses canaux de diffusion, administrés
sous `/admin/sites-de-diffusion`. Ajouter un canal est une mise à jour
technique : la fiche est touchée sans transition de workflow, et l'action de
masse ne fait qu'ajouter des canaux.

`FicheRelance` journalise en append-only les relances d'incomplétude envoyées
aux collaborateurs, avec protection anti-spam. `FicheCollaborateur` porte les
prestataires rattachés à une fiche ; leurs règles d'accès sont décrites dans le
module `Account`.

Le répertoire `Import/` fournit les schémas typés, le générateur de modèle et
le traitement de lignes utilisés par les imports de fiches (module `Etl`) et
par l'extraction documentaire (module `Ocr`).

## Complétude

La configuration est persistée par type de fiche et peut surcharger activation,
poids, formule et longueur cible. Chaque modification crée une révision auditée
et planifie un recalcul asynchrone par lots dans la file `completeness`.

Commandes principales :

```bash
php bin/console app:completeness:sync-config
php bin/console app:completeness:recalculate --type=all --batch-size=250
php bin/console app:completeness:status
```

Un recalcul global n'est terminé que lorsque la commande de statut ne signale
plus aucune fiche en attente.

## Intégrations internes

- `Shared` : outbox, messages, recherche et contrats de stockage ;
- `Dam` : upload, traitement, publication et suppression des médias ;
- `Enrichment` : traduction des fiches publiées et des LOV ;
- `Account` : utilisateurs, JWT, affiliations et autorisations ;
- `Audit` : historique append-only des modifications et transitions ;
- `Ocr` : suggestions d'extraction documentaire arbitrées depuis la fiche ;
- `Etl` : imports de fiches et diffusion marketplace (planifiée à chaque
  indexation).
