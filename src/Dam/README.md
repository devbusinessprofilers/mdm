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
4. Le worker calcule aussi une empreinte perceptuelle de 64 bits, indexée par
   bandes dans `dam_media_phash_band` pour une recherche rapide des images
   proches. Les doublons SHA-256 exacts et les images sous le seuil configuré
   produisent une alerte non bloquante (`dam_media_duplicate_alert`) ; ils
   n'empêchent ni l'enregistrement ni le traitement.
5. `RegenerateMedia`, `DeleteMedia`, `PublishDocument` et `UnpublishDocument`
   couvrent les autres transitions.
6. `ScanDamAnomalies` déclenche `DamAnomalyScanner`, qui persiste dans
   `dam_anomaly` les incohérences détectées (ressources orphelines, rendus
   manquants).

Les handlers sont rejouables : un rendu déjà complet n'est pas régénéré sans
raison, et les suppressions/publications vérifient l'état courant. Après le
dernier échec Messenger, `MediaFailureSubscriber` place le média en erreur avec
un message exploitable.

## Métadonnées et supervision

Chaque photo ou document peut recevoir des mots-clés libres, une source et une
date facultative de fin de droits. Les droits restent valides sans limite quand
aucune date n'est renseignée. Le PIM signale les échéances à J-30 et les droits
expirés sans dépublier automatiquement la fiche. Une source ou une échéance
modifiée révoque cependant la validation existante ; une éventuelle copie
publique du document est alors retirée.

Le prestataire externe peut renseigner la source, les mots-clés et l'échéance,
mais le champ `rightsGranted` lui est refusé. Seul un validateur interne peut
valider ou révoquer les droits depuis `/admin/dam`.

L'écran de supervision regroupe les doublons à revoir, les droits non validés,
à échéance ou expirés, les anomalies détectées ainsi que les traitements en
échec. Les mêmes compteurs sont affichés dans les fiches, et l'écran « Médias »
(`MediasController`) présente la bibliothèque par onglets (imports, droits,
doublons, formats…). Pour les images historiques, lancer :

```bash
php bin/console app:dam:analyze-media --batch-size=250
```

La commande alimente l'outbox ; `worker-dam`, `worker-pim` et `worker-outbox`
doivent être actifs. Un validateur peut accepter un doublon légitime ou retirer
l'image signalée.

## Composants principaux

- `Entity/MediaAsset.php`, `Entity/MediaRendition.php`,
  `Entity/MediaDuplicateAlert.php` et `Entity/DamAnomaly.php` : état
  persistant ;
- `Service/MediaProcessingService.php` : orchestration des rendus ;
- `Service/MediaAnalysisService.php` : pHash et détection des doublons ;
- `Service/DamResourceManager.php` : mutations DAM (acceptation de doublons,
  suppressions, droits) ;
- `Service/DamAnomalyScanner.php` : scan des incohérences ;
- `Service/DamDashboardProvider.php` : supervision des anomalies ;
- `Service/ImageRenditionGenerator.php` : génération ImageMagick ;
- `Service/*Uploader.php` : validation et envoi des originaux ;
- `MessageHandler/` : traitements asynchrones ;
- `Shared/Service/OvhS3ObjectStorage.php` : adaptateur de stockage objet.
