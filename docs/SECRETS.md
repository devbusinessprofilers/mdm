# Gestion et rotation des secrets

Les secrets ne sont **jamais** commités : `html/.env` (suivi) liste toutes les variables avec des valeurs vides ou de dev, les valeurs réelles vont dans `.env.local` (ignoré par git).

## Règles générales

- Renseigner `.env.local` à partir de la liste des variables de `.env` (un `.env.local` minimal est généré au premier démarrage par `Docker/php/local-deploy.sh`). Générer les clés via `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`.
- En production (Upsun) : utiliser les variables d'environnement du projet, marquées sensibles, jamais de fichier `.env.local` déployé :
  ```bash
  upsun variable:create --level environment --name env:APP_SECRET --value '...' --sensitive true
  ```
- Après toute rotation : redémarrer les conteneurs php **et** workers (`docker compose up -d --force-recreate` — le cache Symfony des workers ne se rafraîchit pas seul).

## Procédures de rotation par secret

### APP_SECRET
- Générer : `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`
- Impact : invalide les signatures internes (remember-me, URIs signées Symfony). Les sessions actives et les liens signés en circulation deviennent invalides — à faire hors pic.

### INVITATION_SIGNING_KEY
- Générer comme APP_SECRET.
- Impact : **invalide les invitations en attente**. Prévenir l'équipe et réémettre les invitations en cours après rotation.

### Clés S3 OVH (S3_ACCESS_KEY / S3_SECRET_KEY)
1. Créer une **nouvelle** paire de clés dans l'espace client OVH (Object Storage → utilisateurs S3).
2. Déployer la nouvelle paire (env Upsun / `.env.local`).
3. Vérifier uploads/téléchargements DAM, puis **révoquer l'ancienne paire**.

### Box (BOX_CLIENT_ID / BOX_CLIENT_SECRET)
1. Console développeur Box → application → régénérer le client secret (Box supporte deux secrets actifs simultanément : pas de coupure).
2. Déployer le nouveau secret, vérifier l'OCR (`BOX_OCR_ENABLED=1`), puis supprimer l'ancien secret.

### OPENAI_API_KEY
- Console platform.openai.com → API keys. Alimente la retouche (`images/edits`) et la reconnaissance (vision) des photos, onglets IA de `/medias` ; `OPENAI_ENABLED=0` (défaut) = aucun appel sortant, onglets visibles mais inactifs.
- Rotation libre : créer une nouvelle clé, déployer, supprimer l'ancienne. Penser au force-recreate des workers (`worker-dam` + enrichment) qui portent les appels.

### Clé JWT site externe (EXTERNAL_SITE_JWT_PUBLIC_KEY)
- La clé **privée** est détenue par le site externe consommateur ; côté MDM on ne stocke que la clé publique PEM.
- Rotation : le site externe génère une nouvelle paire RS256, transmet la clé publique par canal sûr ; déployer le nouveau PEM puis le site externe bascule sa clé privée. Prévoir une fenêtre de coordination (une seule clé publique acceptée à la fois).

### GOOGLE_TRANSLATE_API_KEY
- Console Google Cloud → Credentials → créer une nouvelle clé API (restreinte à l'API Translation), déployer, supprimer l'ancienne.

### GEOAPIFY_API_KEY
- Compte myprojects.geoapify.com → projet → API key. Vérification des adresses étrangères (géocodage OSM) ; vide = vérification étrangère désactivée, la BAN (France) continue sans clé.
- Plan gratuit : 3 000 crédits/jour, attribution « © Geoapify / OpenStreetMap contributors » affichée sur les écrans concernés. Rotation libre : créer une nouvelle clé sur le projet, déployer, supprimer l'ancienne.

### METRICS_TOKEN
- Générer comme APP_SECRET. Utilisé en Bearer par l'outil de scrape de `/metrics`. Rotation libre : mettre à jour l'app puis le scraper.

### Salesforce (SALESFORCE_CLIENT_ID / SALESFORCE_PRIVATE_KEY)
- Connected App Salesforce en OAuth JWT Bearer : la clé privée RS256 signe l'assertion, Salesforce détient le certificat public. Mêmes credentials que la marketplace (repo `lamp-docker`, `certs/salesforce_{env}.key`) tant que la Connected App est partagée.
- `SALESFORCE_PRIVATE_KEY` porte le PEM complet (les `\n` littéraux d'une variable mono-ligne sont acceptés) ; `SALESFORCE_USERNAME` est le compte d'intégration (`portail@businessprofilers.fr`), `SALESFORCE_LOGIN_URL` `https://login.salesforce.com` en prod, `https://test.salesforce.com` en sandbox.
- Rotation : générer un nouveau couple clé/certificat, téléverser le certificat sur la Connected App, déployer la clé privée, vérifier `app:salesforce:refresh-fiches --code=<code>` puis retirer l'ancien certificat. Ce refresh tourne aussi en cron quotidien à 3h (Europe/Paris, `src/Schedule.php`) : une clé invalide se voit dès la nuit suivante dans la file `failed`.

### SALESFORCE_WEBHOOK_TOKEN
- Générer comme APP_SECRET. Jeton Bearer attendu par le webhook entrant `POST /api/salesforce/produits` (notification des produits modifiés) ; vide = webhook désactivé (404). À communiquer à l'admin Salesforce (Named Credential / en-tête `Authorization` du callout).
- Rotation libre : déployer le nouveau jeton côté PIM puis mettre à jour le callout Salesforce — pendant l'écart, Salesforce reçoit des 401 et le cron quotidien de 3h rattrape de toute façon les fiches.

### Credentials MariaDB (dépôt parent `.env` : DB_PASSWORD)
- Dev uniquement (conteneur non exposé publiquement). Pour changer : mettre à jour le `.env` racine, recréer le volume SQL ou exécuter `ALTER USER` dans le conteneur, mettre à jour `DATABASE_URL` dans `.env.local`.

## En cas de fuite avérée

1. Révoquer immédiatement le secret côté fournisseur (OVH, Box, Google) **avant** de déployer le remplaçant si possible.
2. Roter tous les secrets présents dans le même fichier/canal fuité.
3. Vérifier l'historique git : `gitleaks detect` (les fichiers `.env.local` sont ignorés depuis l'origine du dépôt — aucune fuite historique connue au 2026-08-18).
