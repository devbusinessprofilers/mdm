# Exploitation de Symfony Messenger

La V1 utilise MariaDB et le transport Doctrine. Les files `pim`, `dam`,
`etl`, `enrichment`, `completeness` et `mail` sont consommées par des processus séparés afin
qu'un traitement lent ne bloque pas les autres domaines. Le processus
`worker-outbox` relaie les événements enregistrés avec les transactions métier.

## Prérequis

La base et les migrations doivent être prêtes avant le démarrage des workers :

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

## Workers locaux

Démarrer l'application, les six consumers Messenger et le relay outbox :

```bash
docker compose --profile workers up -d
```

Afficher leur état et suivre leurs logs :

```bash
docker compose --profile workers ps
docker compose logs --follow worker-outbox worker-pim worker-dam worker-etl worker-enrichment worker-completeness worker-mail
```

Demander un arrêt propre, puis recréer les processus :

```bash
docker compose exec php php bin/console messenger:stop-workers
docker compose --profile workers up -d --force-recreate
```

Après une modification de l’image PHP ou de l’entrypoint worker, reconstruire
toutes les images de workers avant de les recréer :

```bash
docker compose build worker-pim worker-dam worker-etl worker-enrichment worker-completeness worker-mail worker-outbox
docker compose --profile workers up -d --force-recreate
```

Les workers redémarrent aussi automatiquement après une heure, lorsqu'ils
atteignent leur limite mémoire ou après dix erreurs worker consécutives.

Chaque conteneur worker utilise `APP_CACHE_DIR=/tmp/mdm-worker-cache`. Au
démarrage, `worker-entrypoint` vérifie ce chemin et supprime uniquement le cache
de l'environnement courant avant de compiler le conteneur Symfony. Son
conteneur Symfony compilé est ainsi isolé de `var/cache` monté depuis l'hôte :
un `cache:clear`, une analyse statique ou une compilation d'assets ne peut plus
invalider les classes d'un worker déjà en cours d'exécution.

Le worker `dam` requiert aussi la commande ImageMagick `convert`, installée
dans l'image PHP, pour produire les variantes WebP.

Le worker `completeness` calcule les cinq scores après chaque réindexation. Un
recalcul massif est découpé en lots chaînés de 250 fiches par défaut :

```bash
docker compose exec php php bin/console app:completeness:sync-config --type=all
docker compose exec php php bin/console app:completeness:recalculate --type=all
docker compose exec php php bin/console app:completeness:status
```

La synchronisation du catalogue est une étape explicite de déploiement. Les
pages d'administration et les workers ne créent jamais de configuration. Un
changement de catalogue planifie automatiquement le recalcul des types touchés.
Pour le premier rattrapage volumineux, quatre consumers peuvent être lancés
temporairement avec `docker compose --profile workers up -d --scale
worker-completeness=4`, puis ramenés à une instance.

Le site ne doit être ouvert qu'une fois `app:completeness:status` terminé avec
succès, `messenger:failed:show --stats` vide et l'outbox sans événement bloqué.

Les notifications applicatives sont routées vers le transport `mail`, puis le
handler appelle Mailer en mode synchrone dans ce worker. Ne pas router
`SendEmailMessage` vers ce même transport JSON : `RawMessage` n'est pas
désérialisable par le serializer Symfony utilisé par l'outbox.

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

### Jobs d'import de fiches bloqués

Un import de fiches (`/admin/import-fiches`, XLSX ou CSV) est découpé en messages
`ProcessFicheImportBatch` chaînés sur la file `etl`, chaque lot étant commité
avec sa progression (`last_processed_line` sur `etl_import_job`). Si un lot
épuise ses 5 tentatives, le job reste `en_cours` et le message part dans la
file `failed` : le rejeu (`messenger:failed:retry`) reprend exactement au bon
lot, les lignes déjà commitées ne sont jamais retraitées. Un job `echoue`
(fichier illisible, colonnes inconnues, volume > 5000 lignes) se corrige en
réimportant un nouveau fichier.

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
