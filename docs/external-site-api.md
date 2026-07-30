# API du site externe — Lieux

L'ancien contrat GraphQL Provider est supprimé. Le site externe appelle directement l'API REST du PIM avec un JWT de service signé en RS256.

Variables à renseigner dans `.env.local` :

```dotenv
EXTERNAL_SITE_JWT_ISSUER=external-site
EXTERNAL_SITE_JWT_AUDIENCE=mdm
EXTERNAL_SITE_JWT_SUBJECT=external-site
EXTERNAL_SITE_JWT_PUBLIC_KEY=/chemin/vers/public.pem
INVITATION_SIGNING_KEY=une-cle-longue-et-aleatoire
```

Le JWT doit contenir `iss`, `aud`, `sub`, `iat`, `exp` et un `jti` non vide. L'identifiant fonctionnel d'un lieu est son ULID.

## Données

`PATCH /api/lieux/{ULID}` avec `Content-Type: application/merge-patch+json`. Le corps reprend les noms du formulaire Lieu. Les champs inconnus sont rejetés. `status`, `published` et `ressources` ne sont pas modifiables par cette route. Une mise à jour valide publie immédiatement l'état complet de la fiche et demande sa réindexation.

## Médias

- `POST /api/lieux/{ULID}/medias` : multipart, fichier `photo`, avec `usage` et `legende` optionnels.
- `PATCH /api/lieux/{ULID}/medias/{mediaId}` : métadonnées JSON (`usage`, `legende`, `source`, `rightsGranted`, `crop`, `rotation`).
- `PUT /api/lieux/{ULID}/medias/ordre` : JSON `{ "ids": ["..."] }`.
- `POST /api/lieux/{ULID}/medias/{mediaId}/fichier` : remplacement multipart avec `photo`.
- `DELETE /api/lieux/{ULID}/medias/{mediaId}` : suppression.

Chaque mutation média publie directement la fiche et utilise l'outbox pour le traitement DAM et la réindexation.
