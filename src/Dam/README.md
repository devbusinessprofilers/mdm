# Module DAM

Le module `Dam` gère le cycle de vie technique des images et documents :
originaux privés, rendus publics, publication documentaire, suppression et
reprise sur erreur. Les métadonnées métier des ressources restent rattachées aux
fiches PIM.

## Stockage

Les originaux sont envoyés en flux dans le bucket S3 privé configuré. Les
métadonnées persistantes sont conservées dans `dam_media_asset` et les rendus
dans `dam_media_rendition`. Les identifiants S3 sont fournis par l'environnement
ou le gestionnaire de secrets et ne doivent jamais être ajoutés au dépôt.

Les images traitées sont publiées en WebP avec recadrage et rotation éventuels.
Les variantes sont définies uniquement dans `ImageVariantRegistry` :

- `hd` : 1920 × 1080 ;
- `large` : 960 × 480 ;
- `medium_2` : 320 × 190 ;
- `medium` : 300 × 150 ;
- `small` : 200 × 100 ;
- `map` : 194 × 150 ;
- `cart` : 80 × 70.

Les documents restent privés par défaut. Leur publication copie explicitement
l'original vers le stockage public ; leur révocation supprime cette copie. Un
téléchargement privé passe par une URL temporaire autorisée.

## Flux asynchrones

1. Le PIM valide le fichier, persiste `MediaAsset` et place `MediaUploaded` dans
   l'outbox.
2. Le worker `dam` lit l'original privé et produit les rendus attendus.
3. `MediaProcessed` est publié vers le PIM pour confirmer le changement
   technique sur la fiche rattachée.
4. `RegenerateMedia`, `DeleteMedia`, `PublishDocument` et `UnpublishDocument`
   couvrent les autres transitions.

Les handlers sont rejouables : un rendu déjà complet n'est pas régénéré sans
raison, et les suppressions/publications vérifient l'état courant. Après le
dernier échec Messenger, `MediaFailureSubscriber` place le média en erreur avec
un message exploitable.

## Composants principaux

- `Entity/MediaAsset.php` et `Entity/MediaRendition.php` : état persistant ;
- `Service/MediaProcessingService.php` : orchestration des rendus ;
- `Service/ImageRenditionGenerator.php` : génération ImageMagick ;
- `Service/*Uploader.php` : validation et envoi des originaux ;
- `MessageHandler/` : traitements asynchrones ;
- `Shared/Service/OvhS3ObjectStorage.php` : adaptateur de stockage objet.
