# Configuration — paramètres applicatifs et variables d'environnement

L'application distingue deux mécanismes de configuration :

| Mécanisme | Pour quoi | Qui le change | Prise en compte |
|---|---|---|---|
| **Table `parametre`** (écran `/admin/parametres`) | Réglages métier : seuils, interrupteurs de fonctionnalités | Un admin (ROLE_ADMIN), sans redéploiement | Immédiate côté web, ≤ 5 min côté workers |
| **Variables d'environnement** (`.env` + `.env.local`, variables Upsun en prod) | Secrets, infrastructure, config framework, tout ce qui varie par environnement | Un développeur / l'ops | Redémarrage des conteneurs (`docker compose up -d --force-recreate` pour les workers) |

La règle de recouvrement : **la variable d'env est la valeur par défaut, la table `parametre` la surcharge**. Une ligne avec `valeur = NULL` (état initial posé par la migration) est un no-op — l'env s'applique.

## 1. Paramètres applicatifs (table `parametre`)

Réglages exposés dans `/admin/parametres`, lus via `App\Shared\Service\ParametreProviderInterface` (`bool()` / `int()` / `string()`).

| Paramètre | Type | Variable d'env (défaut) | Rôle | Consommateurs |
|---|---|---|---|---|
| `alerte.email` | Texte | `ALERT_EMAIL_TO` (vide) | Destinataire des alertes applicatives (file en échec, erreurs 5xx, workers). **Vide = alertes désactivées.** | `AlertNotifier` |
| `alerte.seuil_file_echec` | Entier | `ALERT_FAILED_QUEUE_THRESHOLD` (5) | Nombre de messages en échec (queue failed Messenger + outbox) à partir duquel une alerte part | `CheckFailedQueueHandler` |
| `box.ocr_active` | Interrupteur | `BOX_OCR_ENABLED` (0) | Active l'extraction OCR des PDF via Box (dépôt, relecture, application des suggestions). Désactivé, les écrans OCR renvoient 404 et l'éditeur de fiche masque la section | `OcrController`, `FicheEditeurEcran` |
| `completude.delai_rappel_jours` | Entier | `COMPLETENESS_REMINDER_COOLDOWN_DAYS` (30) | Délai minimum entre deux relances de complétude pour une même fiche | `RemindIncompleteFichesHandler`, `SendFicheCompletenessReminderHandler` |
| `completude.seuil_rappel` | Entier | `COMPLETENESS_REMINDER_THRESHOLD` (60) | Score de complétude (%) sous lequel une fiche déclenche une relance email. **0 désactive les relances.** | idem |
| `dam.seuil_distance_phash` | Entier | `DAM_PHASH_DISTANCE_THRESHOLD` (8) | Distance pHash maximale pour signaler deux images comme doublons visuels (plus élevé = plus sensible) | `MediaAnalysisService` |

### Fonctionnement

- Les surcharges sont chargées **en une requête** et mises en cache (pool `cache.parametres`, TTL 300 s). L'écran admin invalide le cache à chaque sauvegarde : effet immédiat côté web. Les workers ont leur cache dans `/tmp` (non invalidable depuis le web) : ils suivent au plus tard **5 minutes** après.
- Si la base est indisponible (migration pas encore jouée, incident), le provider retombe sur les défauts env et journalise `parametres.load_failed` — la lecture d'un paramètre ne fait jamais tomber l'appelant.
- « Revenir au défaut » remet `valeur` à NULL : la variable d'env redevient la valeur effective.

### Ajouter un paramètre

1. **Migration** : `INSERT INTO parametre (id, nom, description, type, valeur) VALUES (ulid, 'domaine.nom', '…', 'bool|int|string', NULL)` (voir `Version20260812100000`).
2. **Défaut env** : ajouter l'entrée dans `$defauts` de `App\Shared\Service\ParametreProvider` (`config/services.yaml`) — sans elle, le repli env ne fonctionne pas.
3. **Lecture** : injecter `ParametreProviderInterface` et lire **au moment de l'usage**, jamais dans le constructeur (sinon la valeur est figée dans les workers jusqu'au redémarrage).
4. **Tests unitaires** : utiliser `App\Tests\Support\ParametresFixes`. Les tests d'intégration exigent la table (`doctrine:migrations:migrate --env=test`).

Restent volontairement en env pur : les secrets, la config framework compilée (rate limits, CORS, DSN) et `AUDIT_ENABLED` (consommé par un subscriber Doctrine — le lire en base depuis un listener qui écoute la base créerait une dépendance circulaire).

## 2. Variables d'environnement

Défauts entre parenthèses = valeurs de `html/.env` (suivi par git, valeurs vides ou de dev). Les valeurs réelles vont dans `.env.local` (ignoré) ou dans les variables Upsun en prod. Les variables marquées 🔒 sont des secrets : voir [SECRETS.md](SECRETS.md) pour la génération et la rotation.

### Application

| Variable | Défaut | Rôle |
|---|---|---|
| `APP_ENV` | `dev` | Environnement Symfony (`dev` / `test` / `prod`) |
| `APP_SECRET` 🔒 | valeur de dev | Secret Symfony (CSRF, signatures) |
| `APP_DEBUG` | `1` en dev | Mode debug (forcé à `0` dans les workers) |
| `DEFAULT_URI` | `http://localhost:6080` | URI de base pour générer des URL hors requête HTTP (emails, workers) |
| `CORS_ALLOW_ORIGIN` | regex localhost | Origines autorisées par nelmio_cors |

### Base de données et messagerie

| Variable | Défaut | Rôle |
|---|---|---|
| `DATABASE_URL` 🔒 | MariaDB locale | Connexion Doctrine **et** PdoSessionHandler (sessions en base) |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` | Transport Messenger |
| `TEST_MESSENGER_PIM_DSN` | `in-memory://?serialize=true` | Transport PIM en test ; `doctrine://default` active les tests d'intégration `#[Group('database')]` |

### Email et alertes

| Variable | Défaut | Rôle |
|---|---|---|
| `MAILER_DSN` | `null://null` (`smtp://maildev:1025` en dev local) | Transport d'envoi |
| `MAILER_FROM` | `noreply@businessprofilers.fr` | Expéditeur des emails applicatifs et alertes |
| `ALERT_EMAIL_TO` | vide | **Défaut du paramètre `alerte.email`** (§1) |
| `ALERT_FAILED_QUEUE_THRESHOLD` | `5` | **Défaut du paramètre `alerte.seuil_file_echec`** (§1) |

### Réglages métier (défauts des paramètres applicatifs)

`BOX_OCR_ENABLED` (0), `COMPLETENESS_REMINDER_THRESHOLD` (60), `COMPLETENESS_REMINDER_COOLDOWN_DAYS` (30), `DAM_PHASH_DISTANCE_THRESHOLD` (8) — voir le tableau du §1. Modifier la variable d'env ne sert que de défaut : une surcharge en base prime toujours.

### Sécurité et jetons

| Variable | Défaut | Rôle |
|---|---|---|
| `INVITATION_SIGNING_KEY` 🔒 | vide | Signature des liens d'invitation et de réinitialisation de mot de passe |
| `EXTERNAL_SITE_JWT_ISSUER` / `_AUDIENCE` / `_SUBJECT` | `external-site` / `mdm` / `external-site` | Claims attendus du JWT du site externe (cf. [external-site-api.md](external-site-api.md)) |
| `EXTERNAL_SITE_JWT_PUBLIC_KEY` | vide | Clé publique RS256 du site externe |
| `METRICS_TOKEN` 🔒 | vide | Jeton Bearer protégeant `/metrics` (format Prometheus). **Vide = endpoint inaccessible** |
| `AUDIT_ENABLED` | `1` | Active l'audit Doctrine (tables `audit_*`). Reste en env (voir §1) |

### Rate limiting

| Variable | Défaut | Rôle |
|---|---|---|
| `RATE_LIMIT_API_CLIENT` | `300` | Requêtes/heure par client API authentifié |
| `RATE_LIMIT_API_IP` | `60` | Requêtes/heure par IP sur l'API |
| `RATE_LIMIT_PUBLIC_IP` | `30` | Requêtes/heure par IP sur les pages publiques |

### Stockage S3 / OVH (DAM)

| Variable | Défaut | Rôle |
|---|---|---|
| `S3_ENDPOINT` / `S3_REGION` | OVH `eu-west-par` | Point d'accès objet |
| `S3_PRIVATE_BUCKET` | `bp-dam-originals` | Originaux (bucket privé) |
| `S3_PUBLIC_BUCKET` / `S3_PUBLIC_BASE_URL` | `bp-dam-public` | Variantes servies publiquement |
| `S3_PREFIX` | `dev` | Préfixe des clés par environnement (jamais `reel/`) |
| `S3_ACCESS_KEY` / `S3_SECRET_KEY` 🔒 | vides | Credentials |

### Box (OCR)

| Variable | Défaut | Rôle |
|---|---|---|
| `BOX_CLIENT_ID` / `BOX_CLIENT_SECRET` 🔒 | vides | App serveur Box (client credentials) |
| `BOX_SUBJECT_TYPE` / `BOX_SUBJECT_ID` | `enterprise` / vide | Sujet du jeton |
| `BOX_FOLDER_ID` | `0` | Dossier de dépôt des PDF |
| `BOX_API_URL` / `BOX_UPLOAD_URL` | URLs officielles | Points d'accès API |
| `BOX_ENHANCED_EXTRACT_AGENT` | `enhanced_extract_agent` | Agent d'extraction Box AI |

### Marketplace

| Variable | Défaut | Rôle |
|---|---|---|
| `MARKETPLACE_API_URL` / `MARKETPLACE_API_TOKEN` 🔒 | vides | Invitations de collaborateurs poussées vers la marketplace. **URL vide = envoi journalisé et ignoré (dev)** |
| `MARKETPLACE_SYNC_API_URL` | vide | Synchronisation des fiches (push outbox). **Vide = diffusion désactivée** |
| `MARKETPLACE_SYNC_API_LOGIN` / `_PASSWORD` 🔒 | vides | Compte machine ROLE_PIM (JWT Lexik) |

### Services externes

| Variable | Défaut | Rôle |
|---|---|---|
| `GOOGLE_TRANSLATE_API_KEY` 🔒 | vide | Traduction automatique. **Vide = enrichissement de traduction inopérant** |
| `GOOGLE_TRANSLATE_ENDPOINT` | URL officielle v2 | Point d'accès |
| `RECHERCHE_ENTREPRISES_ENDPOINT` | `https://recherche-entreprises.api.gouv.fr` | Enrichissement SIRET/SIREN (mocké en test) |

### Docker / infrastructure (compose racine `lamp-docker-mdm`)

| Variable | Défaut | Rôle |
|---|---|---|
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASSWORD` / `DB_ROOT_PASSWORD` 🔒 | `.env` racine | Provision du conteneur MariaDB |
| `APP_CACHE_DIR` | `/tmp/mdm-worker-cache` (workers) | Cache Symfony hors bind-mount pour les workers — d'où le `--force-recreate` obligatoire après un changement de constructeur ou d'env |
| `XDEBUG_MODE` | `off` (workers) | Xdebug coupé dans les workers |

### Divers

| Variable | Défaut | Rôle |
|---|---|---|
| `PIM_LIEU_FIXTURE_COUNT` | interne | Nombre de lieux générés par `LieuFixtures` (dev/test) |
| `SYMFONY_TRUSTED_PROXIES`, `SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER`, `SYMFONY_IDE`, `VAR_DUMPER_SERVER`, `TEST_TOKEN` | — | Standard Symfony/outillage (framework, paratest) |
| `URL`, `APP_SHARE_DIR` | vides | **Sans consommateur dans le code** (héritage) — candidates à la suppression |
