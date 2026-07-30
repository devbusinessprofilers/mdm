# DAM

Contexte responsable du cycle de vie des médias, de leur validation, de leurs
rendus, de leur dédoublonnage et de leur publication.

## Stockage des originaux

Les images des lieux sont validées par le formulaire PIM puis envoyées en flux
dans le bucket OVH S3 privé configuré par `S3_PRIVATE_BUCKET`. La clé suit le
format `{S3_PREFIX}/lieux/{lieuId}/{mediaId}/original.{extension}` et les
métadonnées persistantes sont enregistrées dans `dam_media_asset`.

Les identifiants `S3_ACCESS_KEY` et `S3_SECRET_KEY` doivent être fournis dans
`.env.local` en développement et par le gestionnaire de secrets de la plateforme
en production. Ils ne doivent jamais être ajoutés au dépôt.

## Variantes publiques

Après l'événement `MediaUploaded`, le worker `dam` lit l'original privé et
publie des WebP recadrés aux dimensions exactes dans `S3_PUBLIC_BUCKET` :

- `large` : 960 × 480 ;
- `medium_2` : 320 × 190 ;
- `medium` : 300 × 150 ;
- `small` : 200 × 100 ;
- `map` : 194 × 150 ;
- `cart` : 80 × 70.

Les clés suivent le format
`{S3_PREFIX}/lieux/{lieuId}/{mediaId}/renditions/{variante}.webp` et les URL
sont construites depuis `S3_PUBLIC_BASE_URL`. Les remplacements, suppressions
et changements de recadrage ou rotation sont propagés de manière asynchrone.
