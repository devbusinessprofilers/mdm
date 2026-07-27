# Architecture PIM / DAM

Le projet est un monolithe modulaire Symfony. Chaque contexte métier ressemble
à une application Symfony classique, tout en restant isolé dans son dossier.

## Modules

- Pim : fiches prestataires, typologies, workflow, complétude, audit et API.
- Dam : médias, validation, rendus, dédoublonnage et stockage objet.
- Etl : imports, exports, Salesforce, webhooks et publications.
- Enrichment : IA, traduction, OCR, Google Business Profile et scraping.
- Account : utilisateurs, rôles, affiliations et autorisations.
- Shared : contrats, messages et objets partagés stables.

## Structure Symfony d'un module

    Module/
    ├── Command/         # Commandes Symfony Console
    ├── Controller/      # Contrôleurs HTTP et API
    ├── Dto/             # Objets d'entrée et de sortie
    ├── Entity/          # Entités Doctrine et ressources API
    ├── Form/            # Formulaires Symfony
    ├── Message/         # Messages Messenger
    ├── MessageHandler/  # Handlers Messenger
    ├── Repository/      # Repositories Doctrine
    ├── Security/        # Voters et règles d'accès
    └── Service/         # Services métier et contrats

## Règles

1. Un module ne manipule pas directement les entités d'un autre module.
2. Les échanges asynchrones inter-modules utilisent des messages stables dans
   Shared/Message.
3. Les handlers sont idempotents sur leurs identifiants métier.
4. Les dépendances vers la recherche, le cache, le stockage et l'enrichissement
   passent par des interfaces.
5. Les transports Messenger sont séparés par domaine sur MariaDB en V1.
6. RabbitMQ, Redis et OpenSearch restent des adaptateurs V2, sans dépendance
   métier directe.
