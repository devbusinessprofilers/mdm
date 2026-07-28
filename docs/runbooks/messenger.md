# Exploitation de Symfony Messenger

La V1 utilise MariaDB et le transport Doctrine. Les files `pim`, `dam`,
`etl`, `enrichment` et `mail` sont consommées par des processus séparés afin
qu'un traitement lent ne bloque pas les autres domaines. Le processus
`worker-outbox` relaie les événements enregistrés avec les transactions métier.

## Prérequis

La base et les migrations doivent être prêtes avant le démarrage des workers :

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

## Workers locaux

Démarrer l'application, les cinq consumers Messenger et le relay outbox :

```bash
docker compose --profile workers up -d
```

Afficher leur état et suivre leurs logs :

```bash
docker compose --profile workers ps
docker compose logs --follow worker-outbox worker-pim worker-dam worker-etl worker-enrichment worker-mail
```

Demander un arrêt propre, puis recréer les processus :

```bash
docker compose exec php php bin/console messenger:stop-workers
docker compose --profile workers up -d --force-recreate
```

Les workers redémarrent aussi automatiquement après une heure, lorsqu'ils
atteignent leur limite mémoire ou après dix erreurs worker consécutives.

## Diagnostic

Afficher le nombre de messages en attente par transport :

```bash
docker compose exec php php bin/console messenger:stats
```

Le transport `failed` n'est jamais consommé automatiquement. Afficher son
résumé, puis examiner un message précis :

```bash
docker compose exec php php bin/console messenger:failed:show --stats
docker compose exec php php bin/console messenger:failed:show MESSAGE_ID
```

Consulter les logs du worker concerné avant toute action. Une erreur temporaire
doit lever `RecoverableMessageHandlingException`. Une donnée définitivement
invalide doit lever `UnrecoverableMessageHandlingException`.

## Rejeu et suppression

Rejouer uniquement un message identifié et compris :

```bash
docker compose exec php php bin/console messenger:failed:retry MESSAGE_ID --force
```

Supprimer un message uniquement lorsqu'il est irrécupérable :

```bash
docker compose exec php php bin/console messenger:failed:remove MESSAGE_ID --force
```

Le rejeu global de la file est interdit sans validation préalable de son
contenu. Un message correctement acquitté est supprimé automatiquement de la
table Doctrine ; il n'existe donc pas de purge des messages terminés.

## Convention d'idempotence

Chaque message doit porter une clé métier stable. Chaque handler doit pouvoir
recevoir plusieurs fois le même message sans créer de doublon ni répéter un
effet externe. `MediaUploaded` et `MediaProcessed` utilisent `mediaId` comme
clé métier.

La déduplication persistante et la publication transactionnelle seront
assurées par l'outbox. Les effets MariaDB et le reçu de traitement sont validés
dans la même transaction. Un appel vers un système externe doit également
utiliser l'identifiant de `EventIdStamp` comme clé d'idempotence.

## Outbox transactionnelle

Afficher le nombre d'événements par statut :

```bash
docker compose exec php php bin/console app:outbox:stats
```

Afficher les événements que le relay n'a pas réussi à publier, puis en rejouer
un après correction de la cause :

```bash
docker compose exec php php bin/console app:outbox:failed:show
docker compose exec php php bin/console app:outbox:failed:show EVENT_ID
docker compose exec php php bin/console app:outbox:failed:retry EVENT_ID
```

Un événement passe en échec après cinq tentatives. Les délais avant rejeu sont
de 5, 10, 20, 40 puis 60 secondes. Un verrou abandonné par un worker est repris
après cinq minutes.

Purger les événements publiés depuis plus de 30 jours et les reçus de
déduplication depuis plus de 180 jours :

```bash
docker compose exec php php bin/console app:outbox:purge
```

Les événements `pending` et `failed` ne sont jamais purgés automatiquement. Un
message rejoué plus de 180 jours après son premier traitement n'est plus
protégé par le reçu local.

## Tests d'intégration

Avec `DATABASE_URL` pointant vers une base MariaDB de test migrée, exécuter les
scénarios transactionnels avec un transport Doctrine :

```bash
TEST_MESSENGER_PIM_DSN='doctrine://default?auto_setup=0' php bin/phpunit --group database
```
