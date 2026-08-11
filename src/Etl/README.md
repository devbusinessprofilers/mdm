# ETL

Contexte responsable des imports, exports, synchronisations Salesforce,
webhooks et publications vers les systèmes externes.

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
   anti-régression côté marketplace).

### Sémantique

- Un statut `en_cours`/`validee` après modification ne retire PAS la fiche :
  la marketplace conserve le dernier état publié jusqu'à republication.
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
