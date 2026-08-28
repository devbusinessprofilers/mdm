# ETL

Contexte responsable des imports de fiches (fichiers CSV/XLSX et reprise du
legacy) et de la diffusion vers la marketplace. Les exports, synchronisations
Salesforce et webhooks sont des lots ultérieurs : rien de tel n'existe encore
dans le module.

## Import de fiches

`ImportMasseController` (Outils → Import en masse) accepte un classeur XLSX au
format de l'export du référentiel ; chaque feuille de gamme crée un
`FicheImportJob` (mode écrasement) traité par lots de façon asynchrone
(`StartFicheImport` puis `ProcessFicheImportBatch`), avec suivi d'avancement et
détail des erreurs ligne par ligne (`FicheImportDetailController`). Les schémas
de colonnes et la conversion des lignes viennent de `Pim/Import`. Après le
dernier échec Messenger, `FicheImportFailureSubscriber` place le job en erreur.
(L'ancien écran d'import par modèle sous /admin a été retiré.)

La reprise du legacy dispose de commandes dédiées, une par volet :
`app:legacy:import-lieux`, `-restaurants`, `-activites`, `-services`,
`-collaborateurs`, `-photos` et `-translations`. `LegacyFicheMapping` conserve
la correspondance des identifiants historiques et `LegacyPhotoImport` l'état
d'avancement des photos.

## Diffusion marketplace

Pousse les fiches publiées vers la marketplace Business Profilers (push
asynchrone, snapshot complet idempotent). La marketplace est passive : elle
applique les payloads reçus, le PIM décide de tout.

### Flux

1. Toute mutation de fiche enfile `IndexFiche` (module Pim). Son handler
   appelle `MarketplaceSyncScheduler` : point unique de décision.
2. Fiche `publiee` + site `marketplace_bp` sélectionné → `SyncFicheMarketplace`
   (outbox, transport `marketplace`). Fiche archivée ou site désélectionné,
   alors que la marketplace la connaît → `RemoveFicheFromMarketplace`.
3. `SyncFicheMarketplaceHandler` reconstruit le payload à l'envoi
   (`MarketplaceFichePayloadBuilder`) et appelle `PUT /api/pim/fiches/{code}`
   (JWT Lexik, compte machine ROLE_PIM). Le `code` de la fiche est la clé de
   corrélation (= `syspad_id` marketplace).
4. Les traductions disponibles (Google ou correction manuelle) déclenchent une
   resynchronisation (hooks dans le module Enrichment).
5. `FicheMarketplaceSync` (table `etl_fiche_marketplace`) trace l'état
   (synced/failed/removed) et la dernière séquence (ULID, garde
   anti-régression côté marketplace). Après le dernier échec Messenger,
   `MarketplaceSyncFailureSubscriber` y enregistre l'erreur.

### Sémantique

- Un statut `en_cours`/`validee` après modification ne retire PAS la fiche :
  la marketplace conserve le dernier état publié jusqu'à republication.
- Exception : une photo supprimée du PIM est retirée du snapshot même hors
  publication. `MarketplaceSyncScheduler` enfile `PruneMarketplacePhotos`
  (transport `marketplace`), dont le handler envoie la liste des locations
  encore détenues à `PUT /api/pim/fiches/{code}/photos` : la marketplace
  supprime ses lignes `bp_photo` PIM absentes de la liste, sans jamais en
  ajouter — l'éditorial publié et les photos ajoutées en brouillon ne bougent
  pas.
- Obligations photos (`MarketplacePhotoPolicy`, seuils des validateurs de
  soumission : 4 photos pour un Lieu, 1 sinon, plus la photo principale) : si
  le snapshot passe en dessous après une suppression, la fiche est dépubliée
  de la marketplace jusqu'à sa republication PIM. Les fiches encore sur
  imagerie legacy (aucune photo PIM, `removed + remaining = 0` à la purge) ne
  sont jamais dépubliées pour ce motif.
- `DELETE` = dépublication (`is_published=false`), jamais de suppression.
- 409 (séquence dépassée) et 404 (jamais reçue) sont des succès.
- Photos : chemins relatifs des rendus DAM (`photos/{variante}/…`), la
  marketplace compose ses URLs avec ses variables d'environnement.

### Configuration

| Variable | Rôle |
| --- | --- |
| `MARKETPLACE_SYNC_API_URL` | Base de la marketplace (vide = diffusion désactivée) |
| `MARKETPLACE_SYNC_API_LOGIN` / `_PASSWORD` | Compte machine ROLE_PIM (login_check) |

En local : `MARKETPLACE_SYNC_API_URL=http://host.docker.internal:7080` dans
`.env.local` (le docker marketplace expose le port 7080 ; les conteneurs
PIM résolvent `host.docker.internal` via `extra_hosts`). Workers à
force-recreate après changement.

### Reprise

```
php bin/console app:marketplace:sync --all      # reprise initiale
php bin/console app:marketplace:sync --fiche=01H…
php bin/console app:marketplace:sync --failed   # rejoue les erreurs
```

`--all` accepte `--batch` (100 par défaut, 1 à 1000) pour régler la taille des
lots.
