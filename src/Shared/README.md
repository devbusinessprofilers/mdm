# Module Shared

Le module `Shared` contient les contrats et mécanismes techniques communs. Il ne
porte pas de règle fonctionnelle propre à un type de fiche.

## Outbox et fiabilité Messenger

`OutboxPublisher` sérialise chaque message dans `outbox_message` avec un ULID
d'événement, dans la même transaction que la modification métier. Le relais
`app:outbox:consume` revendique les lignes disponibles avec un bail, les publie
vers le transport Messenger configuré et conserve leur état.

Côté worker, `ReceivedDoctrineTransactionMiddleware` exécute le handler et ses
effets dans une transaction Doctrine. `IdempotencyMiddleware` inscrit l'ULID
dans `processed_message` ; un événement déjà reçu n'est pas traité une seconde
fois.

Commandes d'exploitation :

```bash
php bin/console app:outbox:stats
php bin/console app:outbox:consume
php bin/console app:outbox:failed:show
php bin/console app:outbox:failed:retry <event-id>
php bin/console app:outbox:purge
```

Une ligne outbox `published` signifie que le message a été remis à Messenger,
pas que son handler a réussi. Pour confirmer un traitement, il faut aussi
contrôler `processed_message`, les files et le transport `failed`.

## Contrats partagés

- `Message/MediaUploaded.php` et `Message/MediaProcessed.php` : contrats PIM/DAM ;
- `Service/PrivateObjectStorageInterface.php` et
  `PublicObjectStorageInterface.php` : stockage objet ;
- `Service/OvhS3ObjectStorage.php` : adaptateur S3 compatible OVH ;
- `Service/SearchEngineInterface.php` et `Search/` : recherche paginée,
  `BooleanQueryFactory` transforme le texte libre en requête FULLTEXT
  booléenne ;
- `Entity/TimestampableTrait.php` : horodatage Doctrine commun ;
- `Form/` et `Twig/` : composants d'interface transverses.

## Supervision et exploitation

La route publique `GET /health` (`HealthReporter`) renvoie l'identité de
l'application et un statut `ok`, `degraded` ou `down`, avec un test de
connectivité de la base et l'état des files Messenger (messages en attente et
en échec). Elle ne couvre ni les buckets ni l'activité réelle des workers.

`Metrics/` expose `GET /metrics` au format Prometheus, protégé par jeton
Bearer : durée des requêtes HTTP (les requêtes lentes et les erreurs 5xx sont
journalisées) et temps de traitement des messages Messenger.

`Alert/` envoie des alertes par email avec déduplication d'une heure par type
et empreinte : échec définitif d'un message asynchrone
(`WorkerFailureAlertSubscriber`) et dépassement de seuil des files en échec
(`CheckFailedQueueHandler`, Messenger et outbox).

Côté requêtes, `RequestIdListener` propage un ULID `X-Request-Id`,
`RequestContextProcessor` l'ajoute à chaque ligne de log,
`SecurityHeadersListener` pose les en-têtes de sécurité et `RateLimitListener`
limite le trafic par JWT ou IP sur `/api/v1/` et les endpoints sensibles
(réponse 429 avec `Retry-After`).

En développement uniquement, `app:dev:database:clean` vide les tables en
conservant les versions de migration et les listes de valeurs, et purge les
médias des buckets.

## Règles de dépendance

Les messages de `Shared` doivent rester petits, sérialisables et stables. Les
interfaces ne doivent pas importer les entités d'un domaine. Toute nouvelle
règle métier reste dans son module propriétaire ; `Shared` fournit seulement le
contrat ou l'infrastructure réutilisable.

## Handlers Messenger et `flush()`

`ReceivedDoctrineTransactionMiddleware` ouvre une transaction avant tout
handler reçu par un worker et flush à la fin : un handler ne flush pas
lui-même. Deux exceptions, à commenter sur place : un `clear()` qui suit (le
flush sauve ce qui serait perdu), et une mutation qui doit survivre à un
vidage de l'EntityManager par un service appelé ensuite (exporteur, processeur
d'import). Un handler appelé hors bus (commande console, test) doit flush
lui-même après l'appel — c'est l'appelant qui joue le rôle du middleware.
