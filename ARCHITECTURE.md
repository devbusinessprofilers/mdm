# Architecture du MDM PIM / DAM

L'application est un monolithe modulaire Symfony 7.4 avec Doctrine, API
Platform, Twig et Messenger. Les modules partagent le même processus et la même
base MariaDB, mais regroupent leurs contrôleurs, entités, services et messages
par responsabilité fonctionnelle.

## Modules

| Module | Responsabilité actuelle |
| --- | --- |
| `Pim` | Fiches, workflow, LOV, recherche, complétude, administration et API |
| `Dam` | Originaux, rendus d'images, documents et stockage objet |
| `Enrichment` | Traduction des fiches publiées et des LOV |
| `Ocr` | Extraction documentaire Box et suggestions soumises à arbitrage |
| `Account` | Comptes internes, invitations, JWT, affiliations et autorisations |
| `Audit` | Historique append-only des modifications et restauration de champs |
| `Etl` | Imports de fiches (CSV/XLSX, legacy) et diffusion marketplace |
| `Dashboard` | Tableaux de bord, qualité des données et journal des traitements |
| `Shared` | Outbox, contrats inter-modules, supervision et adaptateurs communs |

`DataFixtures` fournit les jeux de démonstration et n'existe qu'en
développement. Le tagging, le scraping, Google Business Profile et les autres
fonctions d'IA ne sont pas livrés ; l'extraction documentaire OCR est portée
par le module `Ocr` (Box, désactivée par défaut), distinct d'`Enrichment`.

## Dépendances

```text
Account ---------> PIM <---------- Enrichment
                     |
                     v
                    DAM
                     |
        tous les modules applicatifs
                     v
                   Shared

Audit observe les modifications Doctrine des domaines métier.
Etl (imports entrants, diffusion marketplace sortante), Ocr
(suggestions arbitrées) et Dashboard (lecture seule) gravitent
autour du PIM.
```

Ces flèches représentent les collaborations réelles, pas des couches totalement
isolées :

- le PIM utilise les uploaders et messages du DAM et le planificateur de
  traduction d'Enrichment ;
- le DAM consulte le rattachement PIM d'un média pour appliquer recadrage,
  rotation et publication documentaire ;
- Enrichment lit les fiches et LOV PIM pour construire ses sources ;
- Account applique les droits sur les fiches et affiliations PIM ;
- Shared ne dépend d'aucun domaine métier.

Les couplages transverses doivent rester dans des services d'orchestration
explicites. Les entités ne doivent pas appeler un worker, un client HTTP ou un
stockage objet directement.

## Structure Symfony d'un module

```text
Module/
├── Api/             # DTO, providers, processors et fabrique OpenAPI
├── Command/         # Commandes Symfony Console
├── Controller/      # Contrôleurs HTTP d'administration
├── Entity/          # Modèle Doctrine
├── Enum/            # Vocabulaires fermés
├── Form/            # FormTypes et fabriques de formulaires
├── Message/         # Messages Messenger propriétaires du module
├── MessageHandler/  # Handlers asynchrones
├── Repository/      # Accès Doctrine/DBAL
├── Security/        # Authentificateurs et voters
└── Service/         # Cas d'usage et orchestration
```

Tous les dossiers ne sont pas obligatoires. Une classe reste dans le module qui
porte sa responsabilité ; un contrat véritablement transversal peut rejoindre
`Shared`.

## Transactions et traitements asynchrones

Les écritures métier et les demandes asynchrones utilisent une outbox
transactionnelle :

```text
requête HTTP/commande
    -> transaction Doctrine
       -> modification métier + outbox_message
    -> relais outbox
    -> transport Messenger par domaine
    -> transaction du handler + processed_message
```

Les transports MariaDB V1 sont séparés : `pim`, `dam`, `etl`, `enrichment`,
`completeness`, `marketplace`, `mail` et `failed`. Les messages récurrents
(statistiques du tableau de bord) passent par les transports `scheduler_*`,
consommés par le service `cron-scheduler`. Les workers ne découvrent pas
eux-mêmes du travail : ils consomment uniquement les messages déjà planifiés. Une ligne
outbox `published` confirme la remise au transport, pas la réussite du handler.

Les handlers reçus sont transactionnels et idempotents grâce à l'ULID
d'événement. Les reprises doivent passer par les commandes dédiées ; les
messages en échec ne sont jamais rejoués implicitement.

## Flux principaux

### Publication et modification d'une fiche

1. Le PIM valide et persiste la fiche.
2. L'indexation et la complétude sont placées dans l'outbox.
3. Si la fiche devient `publiee`, ses traductions sont planifiées.
4. Si une fiche déjà publiée est modifiée, seules les sources traduisibles dont
   l'empreinte a changé sont replanifiées.
5. Aucun balayage automatique de toutes les fiches n'est exécuté ; la commande
   de volume exige une action explicite.

### Image

1. Le PIM valide le fichier et l'envoie dans le stockage privé.
2. `MediaUploaded` est relayé vers le worker DAM.
3. Le DAM produit les variantes WebP dans le stockage public.
4. `MediaProcessed` revient au PIM pour confirmer un changement technique sur
   la fiche rattachée.

### Document

Le document reste privé après upload. Sa publication ou sa révocation est une
transition explicite, asynchrone et distincte du statut de la fiche. Les
mutations provenant d'un site externe préservent le workflow de la fiche.

## Persistance et recherche

MariaDB est la source de vérité pour les entités, les LOV, la complétude,
l'audit, l'outbox et Messenger. La recherche V1 utilise une table dénormalisée
`pim_fiche_search`, un index FULLTEXT et une pagination par curseur. L'interface
`SearchEngineInterface` permet de remplacer cet adaptateur sans modifier les
cas d'usage PIM.

Les originaux binaires sont hors base dans un bucket S3 privé. Les rendus
d'images et documents explicitement publiés utilisent un bucket public.

## Sécurité

- les routes `/admin` sont réservées à `ROLE_ADMIN` ; les flux éditeur et
  validateur vivent sous `/referentiel`, `/recherche` et `/dam`, contrôlés par
  les rôles BP et les voters ;
- les actions sensibles sont contrôlées par `FicheVoter` et les rôles BP ;
- les API de sites externes configurées utilisent un JWT RS256 stateless ;
- les mutations versionnées exigent `If-Match` ;
- les documents privés utilisent des URL temporaires ;
- mots de passe, clés JWT, S3 et Google restent hors Git.

## Règles d'évolution

1. Préserver le workflow lors d'une mutation technique ou externe.
2. Publier les effets asynchrones par l'outbox dans la transaction métier.
3. Rendre les handlers rejouables et vérifier l'état courant avant tout effet.
4. Ne jamais confondre remise au transport et traitement réussi.
5. Conserver les codes de fiches et de LOV immuables ; désactiver plutôt que
   réutiliser une valeur métier.
6. Ne pas annoncer une capacité dans la documentation sans chemin de code,
   persistance et exploitation correspondants.
7. Introduire RabbitMQ, Redis ou OpenSearch comme adaptateurs si la charge le
   justifie, sans déplacer les règles métier hors de leurs modules.
