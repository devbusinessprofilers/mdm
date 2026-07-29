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
- Les workers PIM, DAM, ETL, Enrichment et Mail sont séparés.
- RabbitMQ, Redis et OpenSearch sont reportés en V2 jusqu'à ce que des mesures prouvent leur utilité.

## 2. État actuel

Légende : `[x]` terminé, `[~]` partiel, `[ ]` à faire.

- [x] Socle Symfony, Docker, MariaDB, tests et PHPStan
- [x] Comptes locaux, connexion et rôles BP de base
- [x] Messenger Doctrine, files par domaine, retries, échecs et outbox
- [x] Tronc commun `Fiche`, localisation, statuts et listes de valeurs
- [x] Domaine Lieu, CRUD temporaire, fixtures, pagination et recherche MariaDB
- [~] API Platform installé, API métier à exposer
- [~] Contrats DAM et messages créés, pipeline média à construire
- [ ] Activités
- [ ] Restaurants
- [ ] Services événementiels
- [ ] Traiteurs et Plateaux-repas
- [ ] Imports, exports et synchronisations
- [ ] Dashboards, audit complet et qualité des données
- [ ] Traduction, OCR et enrichissement IA
- [ ] Déploiement Upsun et stockage OVH de production

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

### Étape 6 — Charger des lieux de démonstration

```bash
docker compose exec php php bin/console doctrine:fixtures:load \
  --group=pim-lieux --append --no-interaction --no-debug
```

La liste est ensuite disponible sur <http://localhost:6080/admin/lieux>.

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

1. Finaliser le workflow `en_cours -> validee -> publiee -> archivee`.
2. Ajouter les règles de transition et les droits de validation/publication.
3. Calculer la complétude selon les champs obligatoires et conditionnels.
4. Ajouter un audit en insertion seule : fiche, champ, ancienne valeur, nouvelle valeur, auteur et date.
5. Garder le registre d'attributs pour la gouvernance et les listes de valeurs ; ne pas construire des formulaires entièrement dynamiques de type MaPS/EAV.
6. Ajouter duplication, archivage et restauration ; une suppression définitive reste réservée au Super Admin.

**Terminé quand :** une fiche peut parcourir tout son cycle de vie avec une complétude et un historique fiables.

### Étape 3 — Finaliser les cinq domaines

Utiliser les fichiers Excel du cahier des charges comme source des champs, listes de valeurs, validations et conditions.

1. **Lieu** : terminer les droits, le workflow et l'interface définitive autour du modèle existant.
2. **Activité** : informations générales, type, zone d'action, description, objectifs, capacités et tarifs.
3. **Restaurant** : typologie, cuisine, capacité, localisation, événements, services, photos et tarifs.
4. **Service événementiel** : informations générales, localisation, typologie et informations commerciales.
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

### Étape 8 — Ajouter l'enrichissement et l'IA

1. Garder une interface `EnrichmentProvider` indépendante du fournisseur.
2. Ajouter traduction, reformulation et génération de descriptions.
3. Ajouter OCR et extraction depuis PDF, images et tableurs.
4. Proposer le préremplissage des salles, capacités, équipements et services.
5. Ajouter tagging photo, Google Business Profile et enrichissement public traçable.
6. Marquer chaque proposition comme provisoire avec sa source.

**Règle absolue :** aucun contenu produit par l'IA n'est publié sans validation humaine.

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

## 8. Documentation d'exploitation

- [Workers et files Symfony Messenger](docs/runbooks/messenger.md)
- [Module PIM](src/Pim/README.md)
- [Module DAM](src/Dam/README.md)
- [Module ETL](src/Etl/README.md)
- [Module Enrichment](src/Enrichment/README.md)
- [Module Account](src/Account/README.md)
