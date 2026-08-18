# API REST v1 du site externe — Lieux, Activités, Restaurants et Services

L’API repose sur API Platform 4.3. Son contrat OpenAPI est disponible dans le PIM à
`/api/docs` et au format JSON avec `/api/docs.jsonopenapi`.

La connexion réelle du site externe et la synchronisation des données seront mises
en place dans une phase ultérieure. Le contrat est néanmoins sécurisé dès maintenant
par un JWT de service RS256.

## Authentification

Variables à renseigner dans `.env.local` lors du raccordement :

```dotenv
EXTERNAL_SITE_JWT_ISSUER=external-site
EXTERNAL_SITE_JWT_AUDIENCE=mdm
EXTERNAL_SITE_JWT_SUBJECT=external-site
EXTERNAL_SITE_JWT_PUBLIC_KEY=/chemin/vers/public.pem
```

Le JWT transmis dans `Authorization: Bearer <token>` doit contenir `iss`, `aud`,
`sub`, `iat`, `exp` et un `jti` non vide. L’identifiant fonctionnel d’une fiche est son ULID.

## Lecture et pagination

- `GET /api/v1/lieux` : liste légère, avec `status`, `limit` et `cursor` optionnels.
- `GET /api/v1/lieux/{ULID}` : agrégat complet, médias et URLs des variantes inclus.
- `GET /api/v1/activites` : liste légère des activités, avec les mêmes filtres et la même pagination.
- `GET /api/v1/activites/{ULID}` : activité complète, prestataire LOV, rayon d’action, offres et médias inclus.
- `GET /api/v1/services` : liste légère des services événementiels, avec les mêmes filtres et la même pagination.
- `GET /api/v1/services/{ULID}` : service complet, prestations, zone d’intervention, tarifs et médias inclus.
- `GET /api/v1/restaurants` : liste légère des restaurants avec les mêmes filtres et la même pagination.
- `GET /api/v1/restaurants/{ULID}` : restaurant complet, LOV, localisation, accès, salles et médias inclus.

La liste est triée par date de modification décroissante. Quand une page suivante
existe, son curseur opaque est retourné dans l’en-tête `X-Next-Cursor`.

Chaque détail retourne `version` et un en-tête `ETag`. Toutes les écritures doivent
renvoyer cette version dans `If-Match`. Une écriture concurrente répond `409` avec
la version courante ; un en-tête absent répond `428`.

## Modification

`PATCH /api/v1/lieux/{ULID}` utilise `Content-Type: application/merge-patch+json`.
Le corps reprend les sections du formulaire Lieu. Les propriétés absentes ne sont
pas modifiées. Les champs inconnus ainsi que `status`, `completeness`, `version` et
`medias` sont rejetés.

`informationsGenerales.evenementsPredilection` et
`disponibilites.joursOuverture` sont retournés sous forme de tableaux de codes LOV.
Pendant la transition, l’API accepte également un code unique sous forme de chaîne.

Le site externe ne peut pas créer de fiche. Il ne peut pas non plus modifier le
champ `status`, déclencher une soumission ou publier une fiche. Une modification
conserve strictement le statut et les métadonnées du workflow déjà enregistrés dans
le PIM. Le workflow de validation reste entièrement piloté depuis le PIM.

`PATCH /api/v1/activites/{ULID}` suit le même contrat `merge-patch+json` et
requiert `If-Match`. Il accepte les codes contrôlés `types`, `thematique`,
`langues`, `engagementsRse` et `objectifs`, ainsi que `prestataireCode`. Les zones
mobiles sont des tableaux de textes. Les champs absents ne sont pas modifiés et le
statut est conservé.

`PATCH /api/v1/services/{ULID}` expose le même contrat. Les prestations utilisent
les codes contrôlés de `TYPE_PRESTATAIRE`. En V1, les pays, régions et départements
mobiles restent des tableaux de textes simples ; l’autocomplétion et la minicarte
sont reportées. La description est du texte simple et les cinq tarifs sont des
nombres flottants exprimés en euros.

`PATCH /api/v1/restaurants/{ULID}` accepte les huit listes contrôlées Restaurant,
les horaires, la localisation textuelle V1, les accès, les capacités et les salles.
Les propriétés absentes ne sont pas modifiées et le workflow est préservé. Les
champs Bible 346 `TYPE_FORFAIT` et 347 `NOM_PERSONALISE` ne sont pas exposés :
**champs à ajouter lors du passage au front**.

## Administration des Restaurants

La page `/referentiel/restaurants/fiche` fournit le CRUD, la recherche et le workflow En cours
→ En attente de validation → Publiée → Archivée. Les formulaires sont exclusivement
des FormTypes Symfony rendus avec les helpers Twig ; les collections utilisent
Stimulus depuis `assets/`, sans formulaire HTML manuel ni script inline.

Le groupe `Draft` valide chaque valeur saisie. Le groupe `Submission` ajoute les
champs structurants, les cinq atouts, la localisation, au moins une gare et un
aéroport, les réponses conditionnelles et une à dix photos DAM avec exactement une
photo principale. L’autocomplétion et la minicarte sont reportées au passage au
front. L’historique append-only se consulte sur
`/referentiel/restaurants/fiche/{ULID}/historique`. La commande
`app:restaurants:validate --submission` contrôle les fiches sans les modifier.

## Administration des Activités

La page `/referentiel/activites/fiche` donne accès à la recherche, au CRUD et au workflow
En cours → En attente de validation → Publiée → Archivée. Une sauvegarde utilise
le groupe `Draft`; la soumission utilise aussi `Submission` et exige notamment un
prestataire importé, les champs structurants, une à dix photos traitées et une
seule photo principale. Les formulaires sont exclusivement des FormTypes Symfony.

Les photos sont stockées dans le bucket privé et traitées par le worker DAM. Les
supports commerciaux PDF/JPEG/PNG (100 Mo maximum) sont des documents distincts,
privés par défaut et ne passent jamais dans le générateur de variantes. La LOV
prestataire s’importe avec `app:lov:import-prestataires fichier.csv`; le fichier
UTF-8 doit contenir `code;label`. `--dry-run` contrôle le fichier sans écrire.

Les validateurs ouvrent `/referentiel/activites/fiche/{ULID}/historique` pour consulter l’audit
append-only par champ, sans restauration. Avant un déploiement, exécuter
`app:activites:validate` et ajouter `--submission` pour simuler la soumission.

## Administration des Services événementiels

La page `/referentiel/services/fiche` fournit le CRUD, la recherche et le même workflow que
les autres fiches. Le formulaire est un FormType Symfony rendu exclusivement par
les helpers Twig. La sauvegarde en brouillon accepte une fiche incomplète mais
valide toute valeur renseignée.

Avant soumission, tous les champs métier sont obligatoires. Le mode fixe exige
l’adresse et les coordonnées complètes ; le mode mobile exige au moins un pays,
une région et un département saisis en texte. La fiche doit aussi posséder une à
dix photos DAM traitées, exactement une photo principale et au moins un support
commercial traité, titré, sourcé et assorti de droits validés. L’audit se consulte
sur `/referentiel/services/fiche/{ULID}/historique`. La commande `app:services:validate
--submission` effectue le contrôle sans modifier les données.

## Médias

- `POST /api/v1/lieux/{ULID}/medias` : multipart, fichier `photo`, `usage` et `legende` optionnels ;
- `PATCH /api/v1/lieux/{ULID}/medias/{mediaId}` : `usage`, `legende`, `source`, `rightsGranted`, `crop`, `rotation` ;
- `PUT /api/v1/lieux/{ULID}/medias/ordre` : `{ "ids": ["..."] }`, contenant exactement toutes les photos ;
- `POST /api/v1/lieux/{ULID}/medias/{mediaId}/fichier` : remplacement multipart avec `photo` ;
- `DELETE /api/v1/lieux/{ULID}/medias/{mediaId}` : suppression.

Les mêmes cinq opérations existent sous `/api/v1/activites/{ULID}/medias`.
Une Activité accepte de une à dix photos, les usages `PHOTO_PRINCIPALE` et
`PHOTO_DIVERSE`, et exactement une photo principale avant soumission.

Elles existent aussi sous `/api/v1/services/{ULID}/medias`, avec la même limite
de une à dix photos et exactement une photo principale avant soumission.

Les mêmes opérations existent sous `/api/v1/restaurants/{ULID}/medias`. Les usages
autorisés sont `PHOTO_PRINCIPALE`, `PHOTO_DIVERSE` et `CONFIG_SALLE_PHOTO`. Une
photo de salle reçoit `salleId`, qui doit désigner une salle du même restaurant.

Chaque mutation requiert également `If-Match`, conserve le statut de la fiche, demande
sa réindexation et utilise l’outbox pour les traitements DAM. Les variantes `large`,
`medium_2`, `medium`, `small`, `map` et `cart` sont exposées dans les réponses.

## Documents

- `GET /api/v1/lieux/{ULID}/documents` : liste des documents visibles ;
- `POST /api/v1/lieux/{ULID}/documents` : ajout multipart (`document`, `usage`, métadonnées) ;
- `PATCH /api/v1/lieux/{ULID}/documents/{documentId}` : usage, titre, source, droits et salle ;
- `POST /api/v1/lieux/{ULID}/documents/{documentId}/fichier` : remplacement du fichier ;
- `POST /api/v1/lieux/{ULID}/documents/{documentId}/publication` : `{ "published": true|false }` ;
- `DELETE /api/v1/lieux/{ULID}/documents/{documentId}` : suppression ;
- `GET /api/v1/lieux/{ULID}/documents/{documentId}/download` : URL privée temporaire.

Les routes `/api/v1/activites/{ULID}/documents` proposent les mêmes opérations
liste, ajout, métadonnées, remplacement, publication, suppression et téléchargement.
L’usage est toujours `PJ_SUPPORT_COMMERCIAUX` et aucun rattachement à une salle
n’est accepté.

Les routes `/api/v1/services/{ULID}/documents` proposent également la liste,
l’ajout, la modification des métadonnées, le remplacement, la publication, la
suppression et le téléchargement des supports commerciaux. Toutes les mutations
requièrent `If-Match` et conservent le statut de workflow.

Les routes `/api/v1/restaurants/{ULID}/documents` exposent les mêmes opérations.
Les usages autorisés sont `MENUS`, `CONFIG_PLAN_SALLE` et
`PJ_SUPPORT_COMMERCIAUX`. Un plan exige une salle du même restaurant et reste
limité à deux fichiers par salle. Les menus sont limités à 10 Mo et les supports
commerciaux à 100 Mo.

Les originaux PDF/JPEG/PNG restent dans le stockage privé et ne passent jamais dans
le générateur de variantes. Une ressource publiable reste privée par défaut. Après
validation des droits et publication de la fiche, un worker crée une copie publique ;
l’URL CDN n’est retournée qu’une fois cette copie confirmée.

Scopes JWT : `documents:read` pour les métadonnées, `documents:write` pour les
catégories publiables, `documents:private` pour les pièces confidentielles et
`documents:publish` pour publier ou révoquer. Un ancien jeton sans ces scopes garde
la lecture des documents déjà publiés et ne voit aucune pièce privée.

Les mutations de fiches (`PATCH /api/v1/lieux|activites|services|restaurants/{ULID}`)
requièrent le scope `fiches:write`. Les mutations de médias images (ajout,
métadonnées, remplacement, réordonnancement, suppression) requièrent le scope
`medias:write`. La lecture reste couverte par le seul JWT valide, sans scope.

## Erreurs métier

Les erreurs gérées par le contrat utilisent une structure stable :

```json
{
  "type": "validation_failed",
  "message": "La fiche contient des données invalides.",
  "violations": [
    {
      "propertyPath": "generaleWebsiteUrl",
      "message": "Cette valeur n'est pas une URL valide."
    }
  ]
}
```

Codes principaux : `400` requête invalide, `401` authentification absente ou invalide,
`403` scope insuffisant, `404` ressource inconnue, `409` conflit de version ou de
publication, `413` taille dépassée, `415` format réel interdit, `422` validation
métier et `428` précondition `If-Match` absente.

## Hors périmètre de cette version

Le calcul de complétude, les futures entités après Restaurant, l’échange
des clés avec le site externe, la connexion au site et les synchronisations initiale
ou incrémentale seront traités lorsque le modèle métier sera complet.

La diffusion des fiches vers la marketplace est un flux distinct de cette API
(push outbox → PUT upsert JWT) : voir
[sync-marketplace-referentiel-lov.md](sync-marketplace-referentiel-lov.md).
