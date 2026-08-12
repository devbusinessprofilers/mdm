# Business Profilers MDM — guide de construction

Ce projet construit le référentiel central de Business Profilers. Le MDM regroupe :

- le **PIM** pour les fiches prestataires ;
- le **DAM** pour les images et documents ;
- l'**ETL** pour les imports, exports et synchronisations ;
- l'**enrichissement** pour la traduction, l'OCR et l'IA.

La cible est de supporter **200 000 fiches**, **1 000 000 de médias** et cinq types de prestataires : Lieux, Activités, Restaurants, Services événementiels et Traiteurs/Plateaux-repas.

## 1. Architecture V1

La V1 doit rester simple :

```text
Utilisateurs
    |
    v
Symfony 7.4 + API Platform (Upsun, projet dédié)
    |-- PIM
    |-- DAM
    |-- ETL
    |-- Enrichment
    |-- Account
    |
    +--> MariaDB
    |      |-- données métier
    |      `-- files Symfony Messenger
    |
    `--> OVH Object Storage S3
           |-- dam-originals (privé)
           `-- dam-public (rendus publiés) --> CDN
```

- Le MDM dispose de ressources distinctes de la Marketplace BP.
- MariaDB stocke les données et les messages asynchrones en V1.
- Les files PIM, DAM, ETL, Enrichment, Completeness, Marketplace et Mail restent séparées ; elles sont consommées par cinq workers (outbox, mail, pim, dam et batch pour etl/enrichment/completeness/marketplace), plus le service `cron-scheduler` pour les messages planifiés.
- RabbitMQ, Redis et OpenSearch sont reportés en V2 jusqu'à ce que des mesures prouvent leur utilité.

## 2. État actuel — 12 août 2026

Légende : `[x]` terminé, `[~]` partiel, `[ ]` à faire.

- [x] Socle Symfony, Docker, MariaDB, tests et PHPStan
- [x] Comptes locaux, connexion et rôles BP de base
- [x] Messenger Doctrine, files par domaine, retries, échecs et outbox
- [x] Tronc commun `Fiche`, localisation, statuts, listes de valeurs et validations `Draft`/`Submission`
- [x] Domaine Lieu : CRUD PIM, workflow, recherche MariaDB, contraintes métier et commande de contrôle
- [x] Domaine Activité : CRUD PIM, prestataire, localisation fixe/mobile, offres, workflow, recherche et validation
- [x] Domaine Service événementiel : CRUD PIM, prestations, localisation fixe/mobile V1, tarifs euros, workflow, recherche et validation
- [x] API Platform v1 des Lieux, Activités, Restaurants et Services : lecture, modification, médias et documents avec JWT, scopes, `ETag` et `If-Match`
- [x] DAM images et documents : originaux privés, variantes d’images, publication/révocation documentaire et workers idempotents
- [x] Audit append-only des Lieux, Activités, Restaurants et Services, avec historique filtrable réservé aux validateurs et administrateurs
- [x] Administration technique : supervision outbox/files, catalogue des événements, workflows et routes disponibles
- [x] Restaurants : modèle Bible, LOV, contraintes Draft/Submission, PIM,
  workflow, recherche, audit, DAM, API externe, fixtures et commande de
  validation livrés. L’autocomplétion géographique et la minicarte sont
  reportées au passage au front.
- [ ] Traiteurs et Plateaux-repas : A NE PAS FAIRE POUR LE MOMENT
- [~] Imports : import de fiches Lieu/Activité/Restaurant/Service depuis `/admin/import-fiches` (modèle XLSX par type — feuille Données + feuille Notice & LOV —, upload XLSX ou CSV ; upsert par code ; jobs asynchrones sur la file `etl` avec rapport d'erreurs ligne par ligne), commandes d'import legacy et LOV Prestataire CSV disponibles ; formats XML/JSON, exports et synchronisation Salesforce à faire
- [x] Diffusion marketplace : push asynchrone des fiches publiées vers la Marketplace BP (snapshot idempotent, retrait à l'archivage, reprise par commande — voir `src/Etl/README.md`)
- [~] Dashboards et qualité des données : supervision technique, complétude configurable globale/par canal, tableau de bord d'accueil, pages Qualité et Outils, snapshots planifiés et vue des traitements en échec livrés ; indicateurs Salesforce à faire
- [~] Traduction Google asynchrone des fiches et LOV et vues de suivi livrées ; extraction documentaire des PDF livrée via Box (module `Ocr`, désactivée par défaut) ; le reste de l'enrichissement IA (tagging, génération de contenu) reporté au lot IA
- [ ] Déploiement Upsun et stockage OVH de production

La dernière vérification locale couvre les migrations MariaDB jusqu’à
`Version20260811120000`, un schéma Doctrine synchronisé, 476 tests (3 223
assertions), l’analyse PHPStan, les templates Twig et l’export OpenAPI. Les
formulaires PIM sont construits avec les FormTypes Symfony et rendus par les
helpers Twig ; aucun formulaire métier n’est écrit directement en HTML.

Le contrat détaillé de l’API, du workflow, du DAM et de l’audit est documenté
dans [docs/external-site-api.md](docs/external-site-api.md) et présenté dans la
page `/admin`.

## 3. Démarrer le projet localement

### Étape 1 — Prérequis

Installer Docker avec le plugin Docker Compose, puis cloner le dépôt.

### Étape 2 — Configurer l'environnement

Depuis le dossier `lamp-docker-mdm` :

```bash
cp .env_sample .env
```

Renseigner au minimum :

```dotenv
DB_NAME=mdm
DB_USER=mdm
DB_PASSWORD=un-mot-de-passe-local
DB_PORT=3306
```

Ne jamais commiter de secret ou de mot de passe réel.

### Étape 3 — Construire les conteneurs

```bash
docker compose up -d --build
docker compose ps
```

Services locaux :

- application : <http://localhost:6080> ;
- phpMyAdmin : <http://localhost:6081> ;
- MailDev : <http://localhost:6082>.

### Étape 4 — Créer la base

```bash
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:schema:validate
```

### Étape 5 — Créer le premier administrateur

```bash
docker compose exec php php bin/console app:user:create-super-admin admin@example.com
```

Le mot de passe est demandé sans être affiché. Il doit contenir au moins 12 caractères.

### Étape 6 — Charger les fiches de démonstration

```bash
docker compose exec php php bin/console doctrine:fixtures:load \
  --group=pim-demo --append --no-interaction --no-debug
```

Cette commande crée par défaut 100 Lieux, 100 Activités, 100 Restaurants et
100 Services.
Les volumes se configurent avec `PIM_LIEU_FIXTURE_COUNT`,
`PIM_ACTIVITE_FIXTURE_COUNT`, `PIM_RESTAURANT_FIXTURE_COUNT` et
`PIM_SERVICE_FIXTURE_COUNT`. Les groupes `pim-lieux`, `pim-activites`,
`pim-restaurants` et `pim-services` restent disponibles pour charger un seul
domaine.

Pour repartir d’un environnement vide, arrêter d’abord les workers puis lancer :

```bash
docker compose exec php php bin/console app:dev:database:clean
```

La commande n’existe qu’en environnement `dev`. Elle demande une confirmation,
vide les données applicatives, l’audit, l’outbox et les files Messenger, puis
supprime les objets sous `S3_PREFIX` dans les stockages privé et public. Les
versions de migration et les LOV sont conservées. En exécution automatisée,
ajouter `--force`.

### Étape 7 — Démarrer les workers

```bash
docker compose --profile workers up -d
```

Contrôler leur état :

```bash
docker compose ps
docker compose exec php php bin/console app:outbox:stats
docker compose exec php php bin/console messenger:failed:show
```

## 4. Construire le MDM complet

Toujours terminer une étape avec ses tests avant de commencer la suivante.

### Étape 1 — Terminer les comptes et les droits

1. Ajouter nom, prénom, langue, statut et type de compte.
2. Empêcher deux comptes avec le même email.
3. Permettre à un compte d'être affilié à plusieurs fiches.
4. Ajouter les rôles Super Admin BP, Utilisateur BP, Manager prestataire, Administrateur prestataire et Utilisateur prestataire.
5. Limiter un prestataire à ses seules fiches avec des voters Symfony.
6. Ajouter invitation, activation, réinitialisation du mot de passe et renvoi de l'email.

**Terminé quand :** chaque rôle ne voit et ne modifie que les fiches autorisées.

### Étape 2 — Stabiliser le cœur PIM

Conserver `Fiche` comme tronc commun : identifiant ULID, type, code, libellé, statut, complétude, version et dates. Chaque domaine possède ensuite ses propres entités et tables.

1. Finaliser le workflow
   `en_cours -> en_attente_validation -> validee -> publiee -> archivee`.
2. Ajouter les règles de transition et les droits de validation/publication.
3. Calculer la complétude selon les champs obligatoires et conditionnels.
4. Ajouter un audit en insertion seule : fiche, champ, ancienne valeur, nouvelle valeur, auteur et date.
5. Garder le registre d'attributs pour la gouvernance et les listes de valeurs ; ne pas construire des formulaires entièrement dynamiques de type MaPS/EAV.
6. Ajouter duplication, archivage et restauration ; une suppression définitive reste réservée au Super Admin.

**Terminé quand :** une fiche peut parcourir tout son cycle de vie avec une complétude et un historique fiables.

### Étape 3 — Finaliser les cinq domaines

Utiliser les fichiers Excel du cahier des charges comme source des champs, listes de valeurs, validations et conditions.

1. **Lieu** `[~]` : modèle, CRUD, droits BP, workflow, API, audit et DAM livrés ; les contradictions métier explicitement écartées restent à arbitrer.
2. **Activité** `[x]` : informations générales, prestataire, type, zone d'action, description, objectifs, capacités, tarifs, offres, médias et supports commerciaux livrés.
3. **Restaurant** `[x]` : typologie, cuisine, capacité, localisation en texte
   pour la V1, événements, services, salles, photos, menus, documents, API,
   audit et workflow livrés. Les champs Bible 346 `TYPE_FORFAIT` et 347
   `NOM_PERSONALISE` sont volontairement exclus : **champs à ajouter lors du
   passage au front**.
4. **Service événementiel** `[x]` : informations générales, prestations contrôlées, localisation fixe ou zones mobiles en texte pour la V1, description simple, accessibilité, logistique, tarifs flottants en euros, médias, supports commerciaux, API, audit et workflow livrés. L’autocomplétion et la minicarte sont reportées.
5. **Traiteur/Plateau-repas** : traiteur, produits, variantes, interactions Salesforce et frais de livraison par zone.

Pour chaque domaine :

1. créer les entités et migrations ;
2. importer les LOV du dictionnaire ;
3. ajouter contraintes et champs conditionnels ;
4. créer formulaire, CRUD et projection de liste ;
5. ajouter fixtures et tests ;
6. brancher workflow, audit, complétude et recherche.

Pour les Lieux et Restaurants, afficher une alerte non bloquante lorsqu'une adresse existe déjà.

**Terminé quand :** les cinq types sont créables, recherchables, validables et publiables.

### Étape 4 — Terminer la recherche

1. Indexer code, nom, adresse et contenu utile dans MariaDB Fulltext.
2. Réindexer par message Messenger après chaque modification.
3. Ajouter les filtres par type, statut, pays et complétude.
4. Conserver la pagination par curseur.
5. Tester sans doublon ni omission sur 30 000 puis 200 000 fiches.

OpenSearch ne sera ajouté que si MariaDB ne respecte plus les objectifs ou si des facettes et analyses linguistiques avancées deviennent indispensables.

### Étape 5 — Construire le DAM

#### 5.1 Créer le stockage OVH

Dans un projet OVH Public Cloud, choisir **Object Storage Standard compatible S3**, de préférence en 3-AZ à Paris, puis créer :

- `dam-originals`, bucket privé pour les fichiers sources ;
- `dam-public`, bucket pour les miniatures et rendus publiés.

Créer un utilisateur S3 technique au moindre privilège. Stocker endpoint, région, access key et secret uniquement dans les secrets Upsun ou l'environnement local non versionné. Placer un CDN devant `dam-public`.

#### 5.2 Implémenter les médias

1. Créer `Media`, `MediaRendition`, `MediaUsage` et les métadonnées de droits.
2. Envoyer l'original directement vers le bucket privé avec une URL présignée.
3. Valider type MIME réel, extension, poids et dimensions.
4. Envoyer `MediaUploaded` dans l'outbox.
5. Dans le worker DAM, générer WebP, miniature et format 960 x 480.
6. Déposer les rendus dans `dam-public`, puis envoyer `MediaProcessed`.
7. Permettre classement, photo principale, ordre par glisser-déposer, recadrage, rotation et suppression.
8. Historiser source, droits d'utilisation, consentement, validité, tags et mots-clés.

Contraintes initiales :

- images : PNG, JPG, JPEG ou WEBP, minimum 960 x 480 px ;
- Lieu : 4 à 25 photos, 25 Mo maximum par image ;
- Activité, Restaurant et Service : 1 à 10 photos ;
- Plateau-repas : une photo par plateau, 5 Mo maximum ;
- brochures et plans PDF : 100 Mo maximum ;
- vidéos : URL YouTube ou Vimeo, sans stockage du fichier vidéo en V1.

Calculer SHA-256 et pHash pour **signaler** les doublons sans refuser l'import. Une même image peut donc rester utilisée plusieurs fois si le métier le souhaite.

**Terminé quand :** un original privé produit automatiquement des rendus publics traçables et rattachés à une fiche.

### Étape 6 — Ajouter API, imports et synchronisations

1. Exposer une API REST versionnée avec API Platform.
2. Authentifier avec tokens, permissions et limitation de débit.
3. Importer Excel, CSV, XML et JSON sous forme de jobs asynchrones.
4. Valider chaque ligne, produire un rapport et permettre de reprendre un import en erreur.
5. Exporter par type, filtre et canal cible.
6. Connecter Salesforce en bidirectionnel avec webhooks et réconciliation planifiée.
7. Définir l'application maître **champ par champ** pour éviter les boucles de synchronisation.
8. Publier vers Marketplace BP, Portail Prestataire et sites WordPress.

Tous les appels externes utilisent timeout, retry avec backoff, idempotence, journalisation et file d'échec.

**Terminé quand :** un import et une modification Salesforce arrivent dans le MDM, puis sont publiés sans doublon vers les canaux autorisés.

### Étape 7 — Construire les dashboards

La complétude est calculée pour les Lieux, Activités, Restaurants et Services
événementiels. Le Super Admin configure les poids, la formule et les canaux sur
`/admin/completude`. Les scores global, Marketplace, sites thématiques,
Salesforce et Portail Prestataire sont stockés dans chaque entité métier et
recalculés via la file `completeness`, consommée par `worker-batch`, après la
réindexation.

La formule `presence` attribue tout le poids à une valeur renseignée. La formule
`length_ratio` attribue une fraction du poids selon la longueur cible. Cette
cible provient par défaut de l'attribut `CompletenessTarget` de l'entité et peut
être surchargée dans l'administration sans dépasser la limite de l'entité. Le
champ Photo est rempli uniquement lorsqu'une image DAM traitée dispose des
droits de diffusion. Chaque modification de configuration est historisée avec
son auteur, ses valeurs avant/après et la révision déclenchée.

Le catalogue est synchronisé explicitement avec
`app:completeness:sync-config`. Une modification lance un recalcul par lots avec
`app:completeness:recalculate`, sans charger toutes les fiches dans la file en
une seule fois. `app:completeness:status` affiche la progression et retourne un
code de succès uniquement lorsque toutes les fiches portent la révision
courante. Le premier calcul doit être terminé avant l'ouverture du site.

Afficher au minimum :

- fiches totales, publiées et en attente ;
- complétude globale, par type et par pays ;
- dernières publications et activité utilisateur ;
- volume de modifications et temps moyen de validation ;
- nombre de médias et stockage utilisé ;
- champs les moins renseignés ;
- doublons, incohérences, imports et messages en échec ;
- profondeur des files et âge du plus ancien message.

Ajouter un cron de relance des fiches incomplètes et les alertes d'exploitation.

### Étape 8 — Livrer la traduction ; reporter l'OCR au lot IA

La traduction constitue le socle livré du lot Enrichment. L'extraction
documentaire des PDF est désormais livrée via Box Structured Extract dans le
module `Ocr` (désactivée par défaut, voir `src/Ocr/README.md`). La génération
de contenu et les autres fonctions d'IA restent reportées à un lot IA
ultérieur, après un cadrage métier, juridique, budgétaire et technique
distinct.

#### 8.1 Cadrer les données et les usages

1. Utiliser le français comme langue source. Les langues de la V1 sont le
   français, l'anglais, l'espagnol, l'italien, le néerlandais, le portugais et
   l'allemand, soit les locales `fr`, `en`, `es`, `it`, `nl`, `pt` et `de`.
2. Traduire les contenus métier des fiches : noms, descriptions, légendes,
   informations d'accès et textes commerciaux, y compris ceux des sous-entités
   comme les salles et les offres.
3. Exclure de la traduction les codes, adresses, URLs, emails, crédits et
   sources documentaires, données techniques, commentaires de validation et
   valeurs d'audit.
4. Traduire les libellés des LOV et toutes leurs valeurs dans les six langues
   cibles.
5. Utiliser Google Cloud Translation pour la V1, sans coupler le domaine PIM à
   ce fournisseur.
6. Lister les documents acceptés par l'OCR, leur taille et leur nombre de pages
   maximum, ainsi que les données métier attendues.

#### 8.2 Construire le socle technique

1. Créer des contrats de traduction et d'OCR indépendants des fournisseurs,
   avec un adaptateur Google pour la traduction.
2. Stocker chaque traduction avec sa fiche ou sa valeur LOV, son chemin métier,
   sa locale, la valeur source, son empreinte, la valeur traduite, son origine
   Google ou manuelle, son statut, son erreur et ses dates de traitement.
3. Utiliser les statuts `en_attente`, `en_cours`, `disponible`, `obsolete` et
   `en_erreur`. Une traduction en erreur doit pouvoir être relancée.
4. Exécuter les traitements dans la file Messenger `enrichment`, via l'outbox,
   avec idempotence, timeout, retry avec backoff et file d'échec.
5. Utiliser une empreinte de la source, de la langue cible et de la version du
   fournisseur pour éviter un retraitement identique et détecter les
   traductions devenues obsolètes.
6. Placer le projet, les identifiants et les secrets Google uniquement dans les
   secrets Upsun ou dans l'environnement local non versionné.

#### 8.3 Traduire les fiches publiées

1. Lorsqu'une fiche atteint le statut `publiee`, envoyer dans l'outbox une
   demande de traduction vers `en`, `es`, `it`, `nl`, `pt` et `de`.
2. Rendre automatiquement disponibles les traductions retournées par Google,
   sans bloquer la publication de la fiche.
3. Lors d'une modification puis d'une republication, comparer les empreintes et
   retraduire uniquement les champs français qui ont changé.
4. Permettre aux éditeurs autorisés sur la fiche de consulter les traductions et
   aux validateurs BP de les corriger ou de relancer un traitement en erreur.
5. Ne jamais écraser une correction manuelle. Si la source française change,
   conserver la correction avec le statut `obsolete` et afficher séparément la
   nouvelle suggestion Google.
6. Ajouter depuis chaque vue de fiche un accès **Traductions**. La page affiche,
   pour chaque champ traduisible, le texte français, les six traductions, leur
   origine, leur statut, leur erreur éventuelle et leur date de mise à jour.
7. Journaliser dans l'audit l'auteur et la valeur de chaque correction manuelle.

#### 8.4 Administrer et traduire les LOV

1. Faire de MariaDB la source de vérité des LOV. Les catalogues PHP statiques ne
   doivent plus fournir les valeurs aux formulaires, validations, API ou index.
2. Conserver les codes et identifiants existants pendant cette bascule.
3. Ajouter dans la navigation des validateurs BP une page **Listes de valeurs**,
   paginée et recherchable. Elle affiche le code et le libellé français de
   chaque LOV, son nombre de valeurs et sa couverture de traduction.
4. Ajouter une page de détail par LOV affichant le code, le libellé français et
   les traductions `en`, `es`, `it`, `nl`, `pt` et `de` de chaque valeur, ainsi
   que sa position et son statut actif.
5. Permettre aux validateurs BP d'ajouter une valeur, modifier ses libellés et
   traductions, changer sa position, relancer Google ou la désactiver. La
   création d'une nouvelle définition de LOV n'entre pas dans ce lot.
6. Rendre le code d'une valeur immuable après sa création. Une valeur déjà
   utilisée n'est jamais supprimée : elle est désactivée et reste lisible sur les
   fiches existantes, mais n'est plus proposée pour une nouvelle sélection.
7. Générer les nouveaux identifiants selon le calcul stable actuel et refuser
   toute collision globale avant l'enregistrement.
8. Après l'ajout d'une valeur ou la modification de son libellé français,
   déclencher ses six traductions Google en arrière-plan sans bloquer la page.

#### 8.5 Livrer l'OCR et l'extraction documentaire

> **Statut : livré en V1 pour les PDF via Box Structured Extract (module
> `Ocr`).** La mise en œuvre effective est documentée dans
> `src/Ocr/README.md` ; le plan ci-dessous est conservé comme cadrage pour
> l'élargissement futur (images scannées, extraction native Word/Excel).

##### 8.5.1 Comprendre ce que fait l'OCR

L'**OCR**, ou **reconnaissance optique de caractères**, transforme en texte
exploitable les mots visibles dans une image ou dans un document numérisé. Par
exemple, une fiche technique scannée ne contient pour l'ordinateur qu'une image
de pixels : l'OCR repère les lignes, reconnaît les caractères et restitue le
texte avec sa page, sa position et un indice de confiance.

L'OCR ne doit pas être confondu avec l'IA générative : il retranscrit un contenu
existant, il ne rédige pas de nouveau contenu. Il ne remplace pas non plus
l'extraction native : un PDF qui contient déjà du texte, un document Word ou un
tableur Excel doit être lu directement afin de conserver une meilleure fidélité.
L'OCR est réservé aux images et aux pages scannées. Dans tous les cas, les
valeurs destinées au PIM restent des propositions soumises à validation humaine.

##### 8.5.2 Cadrer le périmètre V1

1. Recenser avec le métier les documents utiles : fiches techniques, menus,
   plans de salles, brochures, attestations et justificatifs RSE.
2. Accepter en V1 les images `JPEG`, `PNG` et `TIFF`, les PDF textuels ou
   scannés, ainsi que `DOCX` et `XLSX` pour l'extraction native. Refuser les
   archives, exécutables et formats non autorisés.
3. Faire valider puis rendre configurables les limites de taille, de pages, de
   résolution et de durée. La proposition initiale est de 20 Mo par image ou
   document Office et de 50 Mo ou 100 pages par PDF.
4. Définir par type de document les informations recherchées et leur chemin
   PIM : capacités, surfaces, horaires, services, équipements, engagements RSE
   ou textes descriptifs.
5. Exclure de la V1 la reconnaissance manuscrite, la traduction automatique du
   document, la génération de texte et toute interprétation libre par un LLM.

##### 8.5.3 Construire le modèle et le pipeline

1. Créer un contrat `DocumentTextExtractorInterface` indépendant du fournisseur
   et deux familles d'adaptateurs : extraction native et OCR. Comparer sur un
   corpus BP un service cloud et une solution locale avant de retenir le
   fournisseur de production.
2. Créer une extraction liée à la fiche, à la ressource DAM et à l'empreinte de
   l'original. Stocker le type MIME, la langue détectée, le fournisseur et sa
   version, le nombre de pages, le statut, l'erreur et les dates de traitement.
3. Conserver pour chaque page le texte et les blocs reconnus avec leurs
   coordonnées, leur ordre de lecture et leur confiance. Placer les résultats
   volumineux dans le stockage objet privé et seulement leurs métadonnées et
   index utiles dans MariaDB.
4. Utiliser les statuts d'extraction `en_attente`, `en_cours`, `disponible`,
   `en_erreur` et `obsolete`. Une nouvelle version du fichier rend l'ancienne
   extraction obsolète sans supprimer son historique.
5. Déclencher un message `ExtractDocumentText` dans la file `enrichment` via
   l'outbox. L'empreinte du fichier, la version de l'extracteur et un identifiant
   de traitement garantissent l'idempotence et empêchent les doublons.
6. Lire uniquement l'original privé autorisé du DAM, au moyen d'une URL
   présignée de courte durée ou d'un flux interne. Le worker ne doit jamais
   rendre le document public pour pouvoir l'analyser.

##### 8.5.4 Produire des propositions PIM traçables

1. Créer des règles explicites et versionnées pour repérer puis normaliser les
   valeurs : nombres et unités, horaires, capacités, listes et libellés de LOV.
2. Enregistrer chaque proposition avec le chemin du champ PIM, la valeur brute,
   la valeur normalisée, la règle utilisée, la confiance, la page et la zone
   d'origine. Les statuts sont `a_valider`, `acceptee`, `rejetee` et `obsolete`.
3. Ne jamais écrire automatiquement dans une fiche. Une acceptation explicite
   applique la valeur au moyen des services et validations du domaine, puis
   crée une révision d'audit avec le document, la page et l'utilisateur.
4. Conserver la valeur déjà présente dans le PIM et signaler les conflits. La
   proposition ne remplace une donnée existante qu'après confirmation humaine.
5. Une proposition rejetée reste consultable pour la traçabilité et n'est pas
   recréée tant que le document, la règle ou le champ source n'a pas changé.

##### 8.5.5 Ajouter les vues de traitement et de validation

1. Ajouter depuis chaque fiche une vue **Extraction documentaire** qui liste les
   documents, leur type, leur version, leur statut, leur date, leur nombre de
   pages et leur erreur éventuelle.
2. Permettre aux utilisateurs autorisés de demander ou relancer une extraction.
   Réserver l'acceptation et le rejet des propositions aux validateurs BP.
3. Afficher côte à côte le document, la zone reconnue, le texte brut, la valeur
   normalisée, le champ PIM ciblé et sa valeur actuelle.
4. Permettre la correction de la valeur proposée avant acceptation, sans
   modifier le texte OCR brut qui constitue la preuve de ce qui a été lu.
5. Ajouter des filtres par statut, document, type de proposition et niveau de
   confiance, ainsi qu'une validation unitaire ou groupée avec confirmation.

##### 8.5.6 Déployer progressivement

1. Constituer un corpus de recette anonymisé couvrant documents propres,
   scans inclinés, faible contraste, tableaux, plusieurs langues et documents
   volontairement invalides.
2. Mesurer séparément la qualité de transcription et la qualité du mapping PIM :
   taux de caractères corrects, champs correctement proposés, faux positifs,
   temps de traitement et coût par page.
3. Commencer par un seul type de document et quelques champs simples, faire
   valider les résultats par le métier, puis élargir progressivement le corpus.
4. Prévoir un interrupteur par environnement, par type de document et par
   fournisseur, ainsi qu'une procédure de reprise des traitements en échec.

**Terminé quand :** un document autorisé peut être extrait de manière
asynchrone et idempotente ; son texte, ses pages et ses zones sont consultables ;
une proposition PIM traçable peut être corrigée, acceptée ou rejetée par un
validateur sans qu'aucune donnée soit publiée automatiquement.

#### 8.6 Sécuriser, tester et superviser

1. Ne pas envoyer au fournisseur un document sans base légale ni droit d'usage ;
   interdire son utilisation pour l'entraînement et limiter sa conservation.
2. Contrôler le type MIME réel, analyser les fichiers avec l'antivirus du DAM et
   maintenir les documents en quarantaine tant que le contrôle n'est pas validé.
3. Chiffrer les originaux et les extractions au repos et en transit, limiter les
   URLs présignées au strict nécessaire et appliquer une durée de conservation
   validée par le métier et le DPO.
4. Ne pas placer le contenu des documents ou les données personnelles dans les
   logs techniques.
5. Tester les droits, l'idempotence, la traduction d'une fiche lors de sa
   publication, la republication partielle, la protection des corrections
   manuelles, les erreurs du fournisseur et la reprise Messenger.
6. Tester l'ajout, la modification, l'ordre et la désactivation d'une valeur
   LOV, l'immuabilité de son code et son utilisation par les formulaires, l'API,
   la recherche et les fiches existantes.
7. Tester les formats et limites OCR, les fichiers corrompus, l'idempotence, le
   changement de version d'un document, les droits, la relance, les conflits de
   champs et la traçabilité des acceptations ou rejets.
8. Mesurer volumes, durées, taux d'erreur, qualité, coût par traitement et âge
   du plus ancien message.
9. Déployer d'abord sur un petit corpus validé par le métier avant d'élargir les
   langues, les types de documents et les volumes.

**Lot traduction terminé quand :** une traduction peut être demandée, traitée
de façon asynchrone, consultée, corrigée et auditée ; les six langues cibles
sont visibles sur chaque fiche publiée et chaque valeur LOV, sans écraser une
correction manuelle. Les critères de fin de l'OCR sont conservés en section 8.5
pour le futur lot IA.

#### 8.7 IA — phase ultérieure

L'OCR et l'extraction documentaire sont reportés dans ce lot avec la
reformulation, la génération de descriptions, le préremplissage intelligent, le
tagging automatique des médias et l'enrichissement depuis des sources publiques.
Aucun fournisseur OCR ni modèle d'IA n'est retenu dans le lot traduction actuel.

**Règle absolue pour cette future phase :** aucun contenu produit par l'IA ne
sera publié sans validation humaine.

### Étape 9 — Durcir et mettre en production

1. Créer un projet Upsun dédié, séparé de la Marketplace.
2. Créer dev, staging et production avec web, MariaDB et workers.
3. Placer tous les secrets dans Upsun.
4. Automatiser migrations, tests, déploiement et retour arrière.
5. Centraliser logs, erreurs, métriques et alertes.
6. Sauvegarder MariaDB et S3 dans une région ou un compte distinct.
7. Tester réellement une restauration.
8. Tester progressivement 30 000 puis 200 000 fiches et jusqu'à 1 000 000 de médias.
9. Vérifier les objectifs : affichage sous 2 s, enregistrement sous 1 s et import de 50 000 lignes sous 10 min.
10. Effectuer les tests fonctionnels, techniques, de droits et de sécurité avant ouverture.

L'antivirus ClamAV sur VPS OVH reste une décision à valider. S'il est retenu, les fichiers restent dans une quarantaine privée et ne sont jamais publiés avant un verdict sain.

## 5. Contrôles obligatoires avant chaque livraison

Depuis `html/` :

```bash
composer analyse
php bin/phpunit
php bin/console doctrine:schema:validate
php bin/console doctrine:schema:update --dump-sql
git diff --check
```

Le SQL de mise à jour doit être vide après application des migrations. Les tests d'intégration doivent utiliser une MariaDB temporaire isolée et jamais la base de développement.

Pour les tests de volume :

```bash
docker compose exec -e PIM_LIEU_FIXTURE_COUNT=30000 php \
  php bin/console doctrine:fixtures:load --group=pim-lieux --append \
  --no-interaction --no-debug
```

Pour tester simultanément les quatre domaines, utiliser le groupe `pim-demo` et
ajouter `-e PIM_ACTIVITE_FIXTURE_COUNT=...`,
`-e PIM_RESTAURANT_FIXTURE_COUNT=...` ainsi que
`-e PIM_SERVICE_FIXTURE_COUNT=...`.

## 6. Quand passer en V2

- **RabbitMQ** : contention MariaDB, files durablement en retard ou besoin de monter les workers en charge indépendamment.
- **Redis** : plusieurs instances web, cache partagé très sollicité, verrous ou rate limiting distribué.
- **OpenSearch** : recherche SQL hors objectif, facettes complexes ou pertinence multilingue avancée.

Ces services doivent remplacer les adaptateurs techniques sans modifier les messages ni les règles métier.

## 7. Sources fonctionnelles

Ce guide synthétise les documents du dossier `Documents BP/Cahier des charges/Drive` :

- `1 - Cahier des charges - MAPS SYSTEM - DATASOLUTION.docx` ;
- `Plan PIM-DAM - V1 simplifiee.pdf` ;
- `Plan PIM-DAM - cadrage simple.pdf` ;
- `BUSINESS P - Dictionnaire & Bible attributs (1).xlsx` ;
- `A - Lieux - Champs.xlsx` ;
- `B - Activités - Champs.xlsx` ;
- `C - Restaurants - Champs.xlsx` ;
- `D - Services Evénementiels - Champs.xlsx` ;
- `E - Plateaux Repas.xlsx`.

En cas de contradiction, faire valider la règle par le métier, l'ajouter dans une décision d'architecture, puis protéger cette décision par un test.

## 8. Arbitrages des workflows du cahier des charges — 31 juillet 2026

Décisions métier prises sur chaque workflow identifié dans le cahier des
charges. Légende : **Oui** = à faire ou à conserver, **Non** = écarté,
**Peut-être** = à arbitrer plus tard.

### Cycle de vie des fiches

- **Oui** — Workflow de statuts `en_cours -> en_attente_validation -> validee -> publiee -> archivee` (déjà livré, à conserver).
- **Non** — Workflow de validation multi-niveaux (CDC §4) : le workflow simple à cinq statuts suffit.
- **Non** — Statuts de publication distincts `non publié / publié` par canal (CDC §6.3).
- **Oui** — Validation bloquée sous le nombre minimum de photos (4 pour Lieux, 1 pour les autres).
- **Oui** — Alerte non bloquante de doublon d'adresse à la création (Lieux et Restaurants).
- **Peut-être** — Workflows paramétrables depuis l'administration (CDC §7).

### Comptes et onboarding

- **Oui** — Onboarding prestataire (CDC §3.1). Attention au périmètre : le compte est **créé sur la Marketplace BP (site externe)**, y compris l'invitation email, l'activation et le mot de passe. Le PIM ne crée pas ce compte : il gère uniquement son existence dans le référentiel (pré-création de la référence) et son rattachement aux fiches prestataires.
- **Oui** — Création d'utilisateur par le Super Admin avec mot de passe auto-généré et email d'activation automatique (CDC §3.2).
- **Oui** — Anti-doublon email : proposition automatique d'affilier la fiche au compte existant et bouton de renvoi des identifiants (CDC §3.2/3.7).
- **Oui** — Modification d'utilisateur avec renvoi d'email d'activation ou de réinitialisation (CDC §3.3).
- **Oui** — Suppression d'un utilisateur (CDC §3.4).

### DAM

- **Oui** — Circuit de diffusion : upload (Portail ou PIM), DAM, génération des URLs, renvoi au PIM, diffusion vers tous les canaux (CDC §A.2).
- **Oui** — Rejet automatique des médias non conformes avec message listant les fichiers refusés et les motifs (CDC §A.2).
- **Oui** — Classement des photos par catégories avec association d'une photo à une salle de réunion précise (CDC §A.2).
- **Oui** — Pipeline asynchrone `MediaUploaded -> workers -> rendus -> MediaProcessed` avec retry puis dead-letter (déjà livré, à conserver).
- **Peut-être** — Workflow antivirus `pending_scan -> clean / infected / scan_error` avec quarantaine ClamAV.

### ETL et synchronisations

- **Oui** — Synchronisation bidirectionnelle Salesforce, MDM et Portail Prestataire avec webhooks et réconciliation (CDC §2/§6).
- **Oui** — Chaîne d'import complète : fichiers Excel/CSV/XML/JSON, transformations, mapping, rapports détaillés avec reprise sur erreur (CDC §6.2).
- **Oui** — Publication omnicanale pilotée par le MDM : Salesforce, Marketplace BP, Portail Prestataires, WordPress, API publiques futures (CDC §6.3).
- **Peut-être** — Traitement des demandes de référencement émanant de Salesforce (CDC §8.1).

### Traduction et OCR

- **Oui** — Traduction Google asynchrone des fiches après publication, du
  français vers l'anglais, l'espagnol, l'italien, le néerlandais, le portugais
  et l'allemand.
- **Oui** — Traduction des libellés et valeurs LOV, avec une vue d'administration
  réservée aux validateurs BP pour ajouter, corriger, ordonner ou désactiver une
  valeur sans modifier son code.
- **Oui** — Vue des traductions depuis chaque fiche, consultation par les
  éditeurs autorisés et correction manuelle par les validateurs BP.
- **Livré en V1 (Box)** — Extraction documentaire des PDF via Box Structured
  Extract, en suggestions arbitrées par un validateur (module `Ocr`, feature
  flag). L'OCR des images scannées et l'extraction native Excel/Word restent à
  faire (CDC §10.2).
- **Oui** — Les traductions Google sont disponibles automatiquement. Une
  correction manuelle n'est jamais écrasée et devient `obsolete` si sa source
  française change.

### IA — phase ultérieure

- **Livré en partie** — l'extraction des PDF et les propositions PIM soumises
  à validation humaine sont livrées (module `Ocr`) ; l'OCR des images scannées
  et l'extraction native Word/Excel restent reportés.
- **Reporté** — Création automatique de fiche avec attribution de la visibilité géographique selon l'adresse (CDC §10.1).
- **Reporté** — Génération et reformulation de textes avec pictogramme « provisoire » et validation humaine obligatoire (CDC §10.2).
- **Reporté** — Préremplissage intelligent des salles, chambres, couverts et équipements à partir des extractions documentaires (CDC §10.2).
- **Reporté** — Tagging automatique des médias par catégories (CDC §10.4).

### Gouvernance et supervision

- **Oui** — Data Governance Workspace : comparatif Salesforce / MDM / Plateforme BP, scoring de qualité, anomalies et notifications (CDC §9).
- **Oui** — Cron de relance des fiches incomplètes avec emails automatiques aux prestataires.

## 9. Documentation d'exploitation

- [Architecture des modules](ARCHITECTURE.md)
- [Workers et files Symfony Messenger](docs/runbooks/messenger.md)
- [Module PIM](src/Pim/README.md)
- [Module DAM](src/Dam/README.md)
- [Module ETL](src/Etl/README.md)
- [Module Enrichment](src/Enrichment/README.md)
- [Module OCR](src/Ocr/README.md)
- [Module Account](src/Account/README.md)
- [Module Audit](src/Audit/README.md)
- [Module Dashboard](src/Dashboard/README.md)
- [Module Shared](src/Shared/README.md)
- [Fixtures de démonstration](src/DataFixtures/README.md)
- [Import legacy — mappings CSV production](docs/import-legacy.md)
- [Configuration — paramètres applicatifs et variables d'environnement](docs/configuration.md)
- [Gestion et rotation des secrets](docs/SECRETS.md)
