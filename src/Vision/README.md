# Vision — retouche & reconnaissance IA des photos

Le module Vision alimente les onglets « Import & retouche » et
« Reconnaissance IA » de l'écran `/medias`. Fournisseur : OpenAI
(`OpenAiImageProvider`, derrière `ImageEnhancementProviderInterface` et
`ImageRecognitionProviderInterface`). Tout est inactif tant que
`OPENAI_ENABLED` vaut 0 (paramètre `openai.actif`, surchargeable à chaud dans
/admin/parametres) : les onglets restent visibles, les actions répondent 404.

## Retouche (`ImageEnhancement`)

Lancement manuel uniquement : un éditeur sélectionne des fiches, chaque photo
traitée part en amélioration globale (luminosité, contraste, couleurs,
netteté — prompt paramétrable `openai.retouche_prompt`, modèle
`openai.retouche_modele`). L'appel `images/edits` est **génératif** : la
candidate est stockée dans le bucket S3 privé
(`…/retouche/{id}.png`, prévisualisée par URL temporaire) et un validateur la
compare à l'original en avant/après.

- **Accepter** : le média bascule sa « source active » (`enhancedStorageKey`
  sur `MediaAsset`) sans toucher l'original ni son checksum (le dédoublonnage
  reste calé sur le fichier déposé), le recadrage/rotation de la ressource est
  réinitialisé (dimensions de sortie différentes), puis
  `ApplyImageEnhancement` (transport `dam`) régénère les renditions et enfile
  `IndexFiche` → re-push marketplace, dans cet ordre.
- **Rejeter** : la candidate S3 est purgée.
- **Revenir à l'original** : `revertToOriginal()` + régénération.

Statuts : `queued → processing → ready → accepted|rejected`, `failed`
relançable. La retouche part toujours de l'original, jamais d'une retouche
précédente.

## Reconnaissance (`ImageRecognition` + suggestions)

Déclenchée automatiquement à l'import d'une photo (hook dans
`MediaUploadedHandler`, gaté par `openai.reco_auto_active`) et manuellement
par sélection de fiches. L'analyse (`chat/completions` vision, sortie JSON
contrainte) lit la rendition publique `large` par URL — pas de transfert de
l'original — et produit légende française, mots-clés, type de vue,
intérieur/extérieur.

Tout reste en suggestions (pattern OCR : `Pending → Accepted/Rejected`,
décisions immuables, valeur corrigible avant acceptation). À l'acceptation :
`legende`/`keywords` de la `RessourceLieu` (la légende remplace, les mots-clés
et le contexte fusionnent), `markChanged()` + `IndexFiche` +
`CalculateFicheCompleteness`.

## Messenger

| Message | Transport |
|---|---|
| `EnhanceImage`, `RecognizeImage` | `enrichment` (appels IA longs) |
| `ApplyImageEnhancement` | `dam` (régénération d'images) |

Erreurs fournisseur 429/5xx → retry transport (delai `retry-after` honoré) ;
erreurs permanentes → statut `failed` porté par le job, pas par la queue
`failed`. Gardes d'idempotence par statut et par checksum source.

## Configuration

`.env` : `OPENAI_ENABLED`, `OPENAI_API_KEY` (secret, env uniquement),
`OPENAI_API_URL`, `OPENAI_IMAGE_EDIT_MODEL`, `OPENAI_VISION_MODEL`,
`OPENAI_RETOUCHE_PROMPT`, `OPENAI_RECO_PROMPT`, `OPENAI_RECO_AUTO_ENABLED`.
Paramètres applicatifs correspondants : `openai.actif`,
`openai.retouche_modele`, `openai.retouche_prompt`, `openai.reco_modele`,
`openai.reco_prompt`, `openai.reco_auto_active` — prompt et modèle sont
figés sur chaque job au lancement (traçabilité).

⚠ Tout changement de constructeur de handler (dont `MediaUploadedHandler`)
exige `docker compose up -d --force-recreate` des workers dam et enrichment.
