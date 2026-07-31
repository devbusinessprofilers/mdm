# API REST v1 du site externe — Lieux

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
`sub`, `iat`, `exp` et un `jti` non vide. L’identifiant fonctionnel d’un lieu est
son ULID.

## Lecture et pagination

- `GET /api/v1/lieux` : liste légère, avec `status`, `limit` et `cursor` optionnels.
- `GET /api/v1/lieux/{ULID}` : agrégat complet, médias et URLs des variantes inclus.

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

Le site externe ne peut pas créer de fiche. Il ne peut pas non plus modifier le
champ `status`, déclencher une soumission ou publier une fiche. Une modification
conserve strictement le statut et les métadonnées du workflow déjà enregistrés dans
le PIM. Le workflow de validation reste entièrement piloté depuis le PIM.

## Médias

- `POST /api/v1/lieux/{ULID}/medias` : multipart, fichier `photo`, `usage` et `legende` optionnels ;
- `PATCH /api/v1/lieux/{ULID}/medias/{mediaId}` : `usage`, `legende`, `source`, `rightsGranted`, `crop`, `rotation` ;
- `PUT /api/v1/lieux/{ULID}/medias/ordre` : `{ "ids": ["..."] }`, contenant exactement toutes les photos ;
- `POST /api/v1/lieux/{ULID}/medias/{mediaId}/fichier` : remplacement multipart avec `photo` ;
- `DELETE /api/v1/lieux/{ULID}/medias/{mediaId}` : suppression.

Chaque mutation requiert également `If-Match`, conserve le statut de la fiche, demande
sa réindexation et utilise l’outbox pour les traitements DAM. Les variantes `large`,
`medium_2`, `medium`, `small`, `map` et `cart` sont exposées dans les réponses.

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
`404` ressource inconnue, `409` conflit de version ou de transition, `422` validation
métier et `428` précondition `If-Match` absente.

## Hors périmètre de cette version

Le calcul de complétude, les futures entités (Restaurant, Activité, etc.), l’échange
des clés avec le site externe, la connexion au site et les synchronisations initiale
ou incrémentale seront traités lorsque le modèle métier sera complet.
