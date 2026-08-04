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
- `Service/SearchEngineInterface.php` et `Search/` : recherche paginée ;
- `Entity/TimestampableTrait.php` : horodatage Doctrine commun ;
- `Form/` et `Twig/` : composants d'interface transverses.

La route publique `GET /health` expose uniquement l'identité de l'application
et un statut `ok`. Elle ne constitue pas un diagnostic de la base, des buckets
ou des workers.

## Règles de dépendance

Les messages de `Shared` doivent rester petits, sérialisables et stables. Les
interfaces ne doivent pas importer les entités d'un domaine. Toute nouvelle
règle métier reste dans son module propriétaire ; `Shared` fournit seulement le
contrat ou l'infrastructure réutilisable.
