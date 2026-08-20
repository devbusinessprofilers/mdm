# Déploiement Upsun — MDM

État de référence : audit du 2026-08-20. Config dans `.platform.app.yaml`,
`.platform/routes.yaml`, `.platform/services.yaml`, `.environment`.

## Ce qui se passe automatiquement à chaque déploiement

Le déploiement est **entièrement piloté par les hooks** : aucune commande
applicative à lancer à la main, ni au premier déploiement ni aux suivants.

| Phase | Commandes | Rôle |
|---|---|---|
| build | `composer install --no-dev --optimize-autoloader` | dépendances + auto-scripts (`cache:clear` → chauffe le cache **prod** grâce à `APP_ENV=prod` déclaré dans `variables.env`, `assets:install`, `importmap:install`) |
| build | `composer run build:assets` | `tailwind:build --minify` + `asset-map:compile` (`public/assets` n'est pas versionné) |
| deploy | `doctrine:migrations:migrate` | schéma — crée aussi les tables `sessions` (PdoSessionHandler) et messenger (`auto_setup=0`) |
| deploy | `app:completeness:sync-config` | idempotent, obligatoire avant tout calcul de complétude |

Au déploiement, Upsun **remplace tous les conteneurs** (web, 5 workers, crons) :
l'équivalent du `force-recreate` obligatoire en Docker local est automatique,
plus aucun risque de worker sur un vieux constructeur.

Tourne en permanence : web (Apache), workers `outbox`, `mail`, `pim`, `dam`,
`batch` (etl + enrichment + completeness + marketplace), et un cron toutes les
15 min qui consomme les schedules Symfony (relances complétude du lundi,
refresh Salesforce 3 h, purges 4 h 20/4 h 25, scan DAM, CheckFailedQueue).

Système de fichiers **en lecture seule** à l'exécution, sauf trois mounts :
`var/log`, `var/import` (dépôts d'import de fiches), `var/cache-app` (pools
filesystem `cache.app` : rate limiter, métriques, paramètres, état scheduler).

## 1. Premier déploiement en production

1. **Projet Upsun** : créer le projet (plan Fixed Large), récupérer le remote
   git (`upsun project:set-remote <id>` depuis `html/`).

2. **Variables d'environnement** (`upsun variable:create --level environment
   --prefix env: --name <NOM> --sensitive true --value '<valeur>'`) — tout ce
   qui est secret ou spécifique à la prod ; les valeurs par défaut non
   sensibles vivent déjà dans `.env` :
   - `APP_SECRET`, `INVITATION_SIGNING_KEY`, `METRICS_TOKEN`
   - `URL`, `DEFAULT_URI` (URL publique du PIM)
   - S3/OVH : `S3_ACCESS_KEY`, `S3_SECRET_KEY`, `S3_PREFIX` (`prod`),
     `S3_PUBLIC_BASE_URL=https://business-profilers.fr` (CDN Cloudflare)
   - Marketplace : `MARKETPLACE_SYNC_API_URL/_LOGIN/_PASSWORD`,
     `MARKETPLACE_API_URL/_TOKEN`
   - Salesforce : `SALESFORCE_CLIENT_ID`, `SALESFORCE_USERNAME`,
     `SALESFORCE_LOGIN_URL`, `SALESFORCE_PRIVATE_KEY`,
     `SALESFORCE_WEBHOOK_TOKEN` (webhook entrant `/api/salesforce/produits`)
   - Mail : `MAILER_DSN`, `MAILER_FROM`, `ALERT_EMAIL_TO`
   - Box/OCR : `BOX_CLIENT_ID/_SECRET/_SUBJECT_ID/_FOLDER_ID`… (si OCR actif)
   - Traduction/géocodage : `GOOGLE_TRANSLATE_API_KEY`, `GEOAPIFY_API_KEY`
     (⚠ absente de `.env`, ne pas l'oublier — géocodage étranger)
   - JWT sites externes : `EXTERNAL_SITE_JWT_PUBLIC_KEY/_ISSUER/_AUDIENCE/_SUBJECT`
   - `DATABASE_URL` et `MESSENGER_TRANSPORT_DSN` sont dérivés automatiquement
     par `.environment` : **ne pas les définir**.

3. **Pousser le code** : `git push upsun <branche-prod>`. Le build + deploy
   décrits ci-dessus s'exécutent ; à ce stade la base est vide mais migrée.

4. **Charger les données** — la prod reprend `mdm_reel` :
   ```bash
   docker compose exec db mysqldump --single-transaction --routines mdm_reel > mdm_reel.sql
   upsun sql < mdm_reel.sql
   ```
   (Alternative from scratch : rejouer l'import legacy, cf. procédure d'import.)
   Puis redéployer ou relancer `app:completeness:sync-config` si le dump a
   écrasé la config de complétude.

5. **Domaine + routes** : attacher le domaine du PIM au projet (les routes
   servent `https://{default}/` et redirigent `www.` vers l'apex), pointer le
   DNS, vérifier le certificat. Mettre `URL`/`DEFAULT_URI` en cohérence.

6. **Salesforce** : poser les secrets et lancer le **premier
   `app:salesforce:refresh-fiches` dans la même fenêtre** (décision go-live) :
   `upsun ssh 'php bin/console app:salesforce:refresh-fiches'`.
   Déclarer l'URL du webhook (`/api/salesforce/produits` + jeton) côté SF.

7. **Marketplace** : vérifier que sa conf pointe le nouveau host PIM
   (API sync) et que sa base d'URL photos est `https://business-profilers.fr`.

8. **Vérifications** :
   - `upsun activity:list` / `upsun activity:log` : build et deploy verts ;
   - connexion `/connexion`, un `/admin` en ROLE_ADMIN, une fiche avec photos ;
   - `upsun ssh 'php bin/console messenger:stats'` : files consommées, pas
     d'accumulation ; `messenger:failed:show` vide ;
   - logs workers : `upsun log app` et `upsun ssh --worker=outbox` etc. ;
   - au premier quart d'heure : le cron scheduler tourne (activity « cron »).

## 2. Mise à jour du code (déploiement basique)

1. Merger sur la branche de prod, puis :
   ```bash
   git push upsun <branche-prod>
   ```
2. Upsun enchaîne tout seul : build (composer + assets) → bascule atomique →
   hooks deploy (migrations, sync-config) → recréation des workers et crons.
   Pas de `force-recreate`, pas de `asset-map:compile` ni de migration à la
   main : tout est dans les hooks.
3. Vérifier :
   - `upsun activity:log` du déploiement (migrations OK) ;
   - smoke test : une page métier + une image ;
   - `messenger:failed:show` si le déploiement touchait des messages/handlers.
4. **Rollback** : `git revert` + push (le code redéploie proprement) ; pour la
   base, restaurer un backup Upsun (`upsun backup:list` / `backup:restore` —
   penser à `upsun backup:create` avant une migration risquée).

⚠ `opcache.validate_timestamps=0` : aucun hotfix de fichier PHP à chaud n'est
pris en compte — toute correction passe par un redéploiement.

## 3. Audit de la config (2026-08-20)

Corrigé ce jour (sans ça, le premier déploiement échouait) :
- **assets jamais compilés au build** (`public/assets` est git-ignoré) →
  ajout de `composer run build:assets` au hook build. Les commandes
  `sass:build` et `tailwind:build …portal.css` du vieux `local-deploy.sh`
  Docker sont obsolètes (bundle sass absent, fichier portal.css supprimé) ;
- **cache prod jamais chauffé** : `.environment` n'est sourcé qu'au runtime,
  le build tournait donc en `APP_ENV=dev` → `variables.env` (APP_ENV/APP_DEBUG)
  désormais visibles au build ;
- **`cache.app` filesystem dans `var/cache` en lecture seule** (rate limiter
  des pages publiques, métriques, surcharges de paramètres, état stateful du
  scheduler) → mount `var/cache-app` + `framework.cache.directory`.

Vérifié conforme : migrations couvrant `sessions` et les tables messenger
(`auto_setup=0`), hook deploy (migrate + sync-config), topologie des workers
alignée sur les transports, schedules tous couverts par le cron 15 min,
redirection `www.`, MariaDB 11.4 alignée sur le `serverVersion` du DSN,
mounts `var/log`/`var/import`, opcache dimensionné.

Points d'attention (non bloquants) :
- disque `db` à 5 Gio : à surveiller après import de `mdm_reel` (photos,
  audit, outbox) — redimensionnable à chaud ;
- `APP_SHARE_DIR` dans `.env` n'est utilisé nulle part (vestige) ;
- PHP 8.4 sur Upsun vs 8.5 en local (commentaire déjà dans le yaml) ;
- si scale horizontal un jour : passer `cache.app` sur Redis (rate limiting
  et paramètres partagés), commentaire déjà en place dans `cache.yaml`.
