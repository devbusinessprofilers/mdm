# Audit 2 — duplication entre gammes (Lieu, Restaurant, Activité, ServiceEvenementiel)

Mesuré le 2026-09-03 sur `dev` par `diff` après normalisation des noms de gamme
(`Lieu|Restaurant|Activite|ServiceEvenementiel → GAMME`) et suppression des
blancs. Les « % communs » sont une borne basse : les fichiers `ServiceEvenementiel*`
étaient formatés différemment (guillemets doubles, retours prettier) avant la
passe php-cs-fixer de la phase 1 ; refaire les diffs sur la branche donnera des
scores plus élevés.

**État après la phase 7 (2026-09-04) :** en plus de la phase 6, `FicheEditeurEcran` est découpé (Editeur\\*), les gabarits Médias/Référentiel/Qualité sont en partiels, `AuditPath` porte la convention des chemins d'audit ; dev (lots portail) est fusionné dans la branche.

**État après la phase 6 (2026-09-04) :** documents, photos, enregistrement du formulaire, validateurs, commandes, API et workflow sont génériques (voir le suivi du rapport). Restent les formulaires, repositories, entités détail et les `*PatchProcessor`/`*ApiMapper` par gamme.

**État après la phase 5 :** les « type → repository → instanceof » passent par
`FicheDetailResolver`, les routes par `FicheRouteResolver`, les libellés par
`TypeFiche`. Tout le reste de ce document est encore d'actualité : c'est le
matériau de la phase 6.

## 0. Architecture réelle

- `Fiche` n'est pas abstraite, pas d'héritage Doctrine : une entité en-tête
  (`pim_fiche`) + une entité détail par gamme (`pim_lieu`, `pim_restaurant`,
  `pim_activite`, `pim_service_evenementiel`) liée en OneToOne et partageant le
  même ULID (`new Fiche(TypeFiche::X, $this->id)` dans les 4 constructeurs).
- Motif dominant : Lieu écrit en premier, les 3 autres gammes copiées, puis des
  génériques `Fiche*` / `Gamme*` introduits pour les 3 nouvelles gammes sans
  reprendre Lieu → systématiquement deux implémentations par brique.

## 1. Chiffres

| Famille | Fichiers (lignes) | Communes | Ce qui diffère vraiment |
|---|---|---|---|
| `Api/State/*MediaProcessor` ×4 | 379/477/367/415 = 1 638 | Activité↔Service 81 %/71 % ; Lieu↔Restaurant 75 %/60 % | Activité et Service identiques hors nom d'entité, `activiteId`/`serviceId`, libellés. Lieu/Restaurant : liste `USAGES` (`LieuMediaProcessor.php:37-46` vs `['PHOTO_DIVERSE','CONFIG_PHOTO_SALLE']`), résolution de salle (`room()` `RestaurantMediaProcessor.php:396-414`), uploader (`LieuImageUploader` vs `FicheImageUploader`), `resource()->lieu()` vs `->fiche()`. Les 4 répètent `assertUsage()` + `usagePrincipaleDeprecie()` (rétrocompat `PHOTO_PRINCIPALE`, 12 occurrences) et le même `preserveWorkflowDuring(function () { … flushAndIndex })`. Aucun test unitaire dédié (couverture via `LieuApiTest`, `RestaurantApiTest`, `RestaurantDocumentUploadTest`, `*OpenApiTest`). Incohérence : ordre de validation des photos différent entre Lieu (`array_diff` deux sens) et Restaurant (`:203-205`), messages 403 `rightsGranted` différents. |
| `Api/State/*DocumentProcessor` ×4 | 374/450/309/349 = 1 482 | Activité↔Service 80 %/70 % ; Lieu↔Restaurant 65 %/55 % | Lieu/Restaurant ajoutent `room()`/`assertMaximum`/`allowedUsage`. |
| `*ItemProvider` ×4 | 4 × 30 | 100 % | Identiques au format près. |
| `*CollectionProvider`, `*DocumentProvider`, `*ApiState`, `*PatchProcessor` | 1 398 | 52–88 % | `ApiState` : même code (`gamme()`, `assertVersion` If-Match, `flushAndIndex`), seuls nom de propriété et message 404 changent. |
| `Api/Dto/*DocumentResource` | 501/168/257 | — | `LieuDocumentResource.php:252-501` contient les 7 opérations `/v1/activites/{activiteId}/documents`. `LieuMediaResource`/`LieuDocumentResource` sont les DTO de toutes les gammes (13 fichiers les importent) → renommer `FicheMediaResource`/`FicheDocumentResource`, sortir les opérations Activité (aucun changement d'URL). |
| `Api/Dto/*Resource` (fiche) | 373/252/362/358 | Lieu↔Activité 76 %/78 % | 9 propriétés d'en-tête + `$localisation` + `$medias` recopiées 4× ; blocs OpenAPI (status enum, cursor, limit) recopiés 4×. |
| `Service/*AdminManager` | 115/114/112/112 | Activité↔Service 99 % (1 ligne : le `use`) | boucle photos, `array_diff → DeleteMedia`, `persist+schedule+IndexFiche` ×4 ; Activite/Service `flush()` là où Lieu/Restaurant passaient sous `preserveWorkflowsDuring` (depuis la phase 5 : `FicheMutation::enregistrerAvecLiees`). |
| `Controller/*Controller` (workflow) | 313/280/279/225 = 1 097 | 47–68 % | 4 × 8 actions identiques (`delete/submit/validate/publish/reject/archive/unarchive/republish`) : `find` + `instanceof` + voter + `FicheActionFormFactory` + workflow + redirect. Regex ULID écrit 61 fois dans le dossier. Divergences : libellés anglais hors Lieu (« Validate » `ActiviteController.php:128`, Restaurant :107, Service :127, « Archive »), flashes de succès uniquement pour Lieu, 49 imports fossiles. Archive/delete unifiés en phase 2. 0 test fonctionnel sur ces 32 routes. |
| `Controller/*DocumentController` | 169/161/129/129 | Activité↔Service 94 % | `download()` identique ×4 + `original()` ×2 = 6 exemplaires de `find ?? 404 ; temporaryUrl('+10 minutes')` ; try/catch présent dans Restaurant :82-86 / Lieu :116-117, absent dans Activite/Service :53-55 → une `DomainException` donne 500 d'un côté, un message de l'autre ; 2 jeux de messages. |
| `Controller/LieuPhotoController` vs `GammePhotoController` | 190 + 297 | 45 % | Mêmes 9 actions, même `LieuPhotoManager`, même `InternalFicheMutationPolicy` ; 10 blocs `resolve+404+deny` ; jeton CSRF `headers->get('X-CSRF-TOKEN', …)` écrit 16 fois ; `GammePhotoController::modales()` :262-296 recopie `LieuAdminViewBuilder::modalesVars()` :101-118. |
| `Form/*Type` | 182/463/444/397 | 30–40 % | Lieu déclaratif (`LieuFormCatalog` + `MethodMappedFieldsType`) ; les 3 autres impératifs avec `field()`, `collectionAccessors()`, `booleanField()` recopiés (`RestaurantType.php:378-462`, `ActiviteType.php:421`, `ServiceEvenementielType.php:322-396`, `LieuType.php:108-172`) ; paire `businessPremium`/`partenaireBp` Salesforce `LieuType.php:38-54` ≡ `RestaurantType.php:57-72`. Les 4 `*TypeTest` existent. |
| `Form/*RessourceType` | 143/161/137 | Restaurant↔Activité 76 %/89 % | |
| `Form/*AutocompleteType` | 42/42 | 97 % (préfixe `LIE-`/`RES-`) | |
| `Validation/Valid*Validator` | 444/370/347/332 = 1 493 | 27–44 % | même squelette (longueurs, URL, lien vidéo, photos + maximum, `submission()`), helpers renommés à chaque copie : `length()` :429 / `maximumLength()` :356 / `maxLength()` :333 / `maximumLength()` :312 ; bloc ressources/photos `ValidLieuValidator:306-376`, `ValidServiceEvenementielValidator:100-125`, `ValidRestaurantValidator:337-347` ; 0 test unitaire par validateur. |
| `Repository/*Repository` | 243/208/177/146 | Activité↔Restaurant 80 %/69 % | `findOneByFiche`, `findBatchAfter`, `findListPage`, `countByStatus`, `findListItemsByIds` recopiés. |
| `Command/Validate*Command` | 68/97/11/105 | 66 % | 4 copies → `app:fiches:validate --gamme=` via `FicheDetailResolver`. |
| `Dam/Service/LieuImageUploader` vs `FicheImageUploader`, `LieuDocumentUploader` vs `FicheDocumentUploader` | 446 | 65 % / 86 % | Différence : `Lieu $lieu` vs `Fiche $fiche`, préfixe de clé S3 (`lieux/` vs `FicheImageUploader::segment()`). Décision phase 2 : segment par gamme partout. |
| `Service/LieuDocumentManager` vs `FicheDocumentManager` | 176/190 | 64 %/59 % | mêmes 5 méthodes (`replaceWithinMutation`, `togglePublication`, `deleteWithinMutation`, `unpublish`, `withinMaximum`) ; différences : `Salle` vs `RestaurantSalle`, `update` Lieu peut changer l'usage. 0 test `*DocumentManagerTest`. |
| `Salle`↔`RestaurantSalle`, `PeriodeFermeture`↔`RestaurantPeriodeFermeture`, `AccesLieu`↔`RestaurantAcces` | 695 | `Salle`/`RestaurantSalle` : 17 colonnes, 33 méthodes identiques | `RessourceLieu` porte deux colonnes exclusives `salle_id` et `restaurant_salle_id` (`RessourceLieu.php:57-61, 249-266`). |
| Entités détail | Lieu 1 494 / Restaurant 624 / Activité 543 / Service 553 | 41–54 % entre les 3 petites | recopiés : `id/fiche/code/label/changeLabel/localisation/changeLocalisation/ressources/addRessource/removeRessource/markChanged/touch/lovValues|attributeValues/replaceLov|replaceAttributeValues/normalize*`. |

≈ 9 000 lignes « par gamme » dans `src/Pim` (+ 800 dans Dam/Audit/Etl), dont la
moitié est du copier-coller structurel.

## 2. Dispersion des « switch par gamme » (avant phase 5)

- 135 `instanceof (Lieu|Restaurant|Activite|ServiceEvenementiel)` dans 43 fichiers :
  `DoctrineAuditSubscriber.php` (16 — 4 blocs identiques l.102-145, puis 412-415
  et 543-553), `FicheEditeurEcran.php` (12 + 21 `TypeFiche::`),
  `SalesforceProduitsCsvExporter.php` (10), les 4 contrôleurs workflow (8 chacun),
  `FicheCreationManager.php` (6 + 10), `FicheWorkflowManager.php:127-135`,
  `Fusion/FicheFusionneur.php`, `MarketplaceFichePayloadBuilder.php:110-122`,
  `Enrichment/FicheTranslationSourceExtractor.php:45-55`.
- 34 `match` sur le type dans 27 fichiers ; 208 comparaisons `TypeFiche::` dans 57 fichiers.
- Liste des champs d'une gamme connue de 16 fichiers hors Entity/Form/Validation
  (grep `descGenerale`) : `FicheSectionsCatalogue`, `CompletenessFieldCatalog`,
  `LieuImportSchema`, `FicheExportColonnesCatalogue`, `FusionChampsCatalogue`,
  `TextDuplicateFields`, `MarketplaceFichePayloadBuilder`,
  `SalesforceProduitsCsvExporter`, `FicheTranslationSourceExtractor`,
  `FicheSearchIndexer`, `LieuObligationsPublication`,
  `EnrichissementSuggestionArbitre`, `EnrichirFicheHandler`, `DataTourismeVerifier`,
  `ImportLegacyTranslationsCommand`, `Audit/RestorableFieldCatalog`.

## 3. Lieu.php (1 494 lignes)

50 colonnes scalaires, 22 attributs LOV (chaque paire = 10 lignes pour appeler
`lovValues`/`replaceLovValues`), 74 `change*()`, 90 getters, 47 commentaires
« Bible row » sans colonne. Groupes : générale, disponibilités, PMR, TA,
description + `atout1..5` (5 colonnes alors que Restaurant stocke `atouts` en
liste — `EnrichissementSuggestionArbitre.php:173-178` fait un `match` d'index),
hébergement (7), synthèse salles (8), services/technique/installation/bien-être
(LOV), RSE, loisirs (3 listes JSON, normalisation recopiée 3× `:1208-1241`),
restauration (11 + 2 LOV), visibilité. Plus : liaison Lieu↔Restaurant
(`trackFicheLiee`/`drain…` `:383-433`, dupliqué `Restaurant.php:205-219`),
miroir de la collection `ressources` avec `fiche->resources` (`:562-585` ; 54
appels `->ressources()` contre 17 `->resources()`), `accesParType()` + 5 lecteurs
pour la complétude (`:518-554`).

Sorties possibles sans changer le schéma : embeddables Doctrine avec
`columnPrefix: false` (`LieuHebergement`, `LieuSallesSynthese`,
`LieuRestauration`, `LieuRse`, `LieuLoisirs`, `LieuDescription`) contrôlés par
`schema:validate` sans diff ; accès LOV générique `lov(code)`/`changeLov(code,
valeurs)` (les formulaires passent par des noms de méthode en chaîne dans
`LieuFormCatalog`) ; `ressources()` alias de `fiche->resources()` ;
`MappedSuperclass` pour Salle/PeriodeFermeture.

## 4. Codes d'usage photo

`CONFIG_SALLE_PHOTO` (Restaurant) vs `CONFIG_PHOTO_SALLE` (catalogue) : résolu en
phase 2 (code unique `PhotoUsageCatalog::SALLE`).

## 5. Reliquats

- `CompletenessScoresTrait::applyCompleteness()` : supprimé en phase 1.
- `PHOTO_PRINCIPALE` : rétrocompat `usagePrincipaleDeprecie()` recopiée dans les 4
  processeurs média → à centraliser dans `PhotoPrincipale` avec la fusion des
  processeurs.
- `Lieu::generaleGamme` `@deprecated` (`:661`), encore lu par
  `MarketplaceFichePayloadBuilder` ; `generaleGammeLibelle` par 4 fichiers.
- Amplitude horaire : colonnes droppées mais `heureOuverture`/`heureFermeture`
  conservés en entrée/sortie API (`RestaurantPatchInput.php:57-65`,
  `RestaurantResource.php:228-230`) — rétrocompat portail documentée.
- `TypeFiche::Traiteur` : branches mortes dans `OcrCategoryPolicy:23`,
  `FicheExportXlsxGenerator:49`, `FusionChampsCatalogue:91`, `ReferentielEcran:66-70`,
  `FicheSectionsCatalogue:33`, `FicheDuplicateDetector:64` → `estOperationnel()`.
- Catalogues LOV statiques (`LieuLovCatalog::CHOICES` ≈ 450 l., etc.) = repli quand
  `LovRuntimeCatalog` est vide ; 4 API différentes (`choicesFor`/`values`,
  `assertValidMany`/`valueIds`, `stableIntegerId`/`stableId`).

## 6. Ordre de simplification (phase 6 et suivantes)

1. Filet : 4 tests de non-régression validateurs (violations attendues sur une
   fixture) ; rejouer `LieuApiTest`/`RestaurantApiTest`.
2. Activité + Service : `GammeAdminManager` générique (constructeur et `save()`
   identiques), `FicheDocumentController` paramétré par un profil de gamme
   (préfixe, type de métadonnées, usage imposé, salles), `DocumentsModalesVue`
   unique (`*AdminViewBuilder::form()` mortes ×4), `app:fiches:validate --gamme`,
   `FicheAutocompleteType` avec option `prefixe`.
3. Restaurant, puis Lieu : supprimer les versions `Lieu*` en faisant passer Lieu
   par les génériques (`FicheImageUploader::upload($file, $lieu->fiche())` avec
   segment par gamme), étendre `GammePhotoController` à `lieux`, renommer
   `LieuPhotoManager` → `FichePhotoManager`.
4. Processeurs et providers API génériques (−2 500 lignes) via
   `FicheDetailResolver` + profil de gamme ; renommage `LieuMediaResource` →
   `FicheMediaResource`.
5. `FicheTransitionExecutor` + contrôleur de workflow unique
   `/referentiel/{gamme}/fiche/{id}/{transition}` (conserver les noms de routes
   générés par `FicheActionFormFactory:30,105`).
6. Entités : alias `Lieu::ressources()`, embeddables, `MappedSuperclass`, accès LOV
   générique (contrôlé par `doctrine:schema:validate` sans diff).
7. Formulaires et validateurs déclaratifs, une gamme à la fois, `*TypeTest` en garde-fou.
