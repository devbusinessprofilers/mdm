# OCR / Extraction documentaire Box

Le module OCR ajoute un historique d'extractions documentaires aux fiches Lieu,
Activité, Restaurant et Service événementiel. Le PDF original est conservé dans
le DAM privé. Les copies temporaires envoyées à Box sont supprimées après chaque
lot de cinq pages.

L'extraction est asynchrone sur le transport Messenger `enrichment`. Elle utilise
le schéma typé de `FicheImportSchemaRegistry`, appelle Structured Extract avec les
confiances et références, puis crée uniquement des suggestions. Aucune donnée
n'est appliquée ou publiée automatiquement.

## Configuration

La fonctionnalité est désactivée par défaut. Les variables suivantes doivent être
injectées par le gestionnaire de secrets de l'environnement :

- `BOX_OCR_ENABLED=1`
- `BOX_CLIENT_ID`
- `BOX_CLIENT_SECRET`
- `BOX_SUBJECT_TYPE` (`enterprise` ou `user`)
- `BOX_SUBJECT_ID`
- `BOX_FOLDER_ID`
- `BOX_API_URL`
- `BOX_UPLOAD_URL`
- `BOX_ENHANCED_EXTRACT_AGENT`

Le dossier Box doit être technique et privé. Aucun secret ne doit être ajouté au
dépôt ni aux journaux.

## Déploiement et recette

1. Construire l'image PHP, qui installe `poppler-utils` (`pdfinfo`,
   `pdfseparate`, `pdfunite`).
2. Exécuter les migrations Doctrine.
3. Redémarrer le worker `enrichment` avec la nouvelle image et vérifier les
   transports Messenger, l'outbox et le transport d'échec.
4. Configurer Box, conserver le feature flag à `0`, puis effectuer une recette
   avec des PDF français anonymisés de 1, 5, 6 et 100 pages.
5. Activer le flag après validation des champs, LOV, tableaux, références de pages,
   suppressions Box et droits d'accès.

La V1 conserve la réponse structurée, les confiances et les références du
fournisseur. Elle ne conserve ni transcript OCR intégral ni coordonnées
géométriques. La prise en charge effective du français doit être confirmée sur le
corpus de recette avant l'ouverture générale.
