# Audit 3 — services, contrôleurs et composants Twig du module Pim

Mesuré le 2026-09-03 sur `dev`. Les composants Twig morts (E4) ont été supprimés
en phase 1 ; `FicheEditeurEcran::slug()`, les ternaires de route et la gate OCR
(M4, M6) ont été traités en phases 2 et 5. M3, E2 et une partie de M5 sont traités en phase 6, E1 (FicheEditeurEcran) et M1 (Geoapify, ClientHttpLisse) en phase 7 (2026-09-04) ; restent M2, M5 (logique dans les contrôleurs), M7 et le renommage Enrichment → Translation.

## Réponses courtes

| # | Question | Mesure |
|---|---|---|
| 1 | 98 services | 7 clients HTTP/fichiers (≈1 500 l.) · 17 classes « écran » (3 360 l.) · 14 `*Manager` + 8 orchestrateurs · 9 `*Verifier` d'enrichissement · 12 classes de recherche (1 315 l.) · 13 utilitaires · 10 DTO · 4 interfaces. Le cluster « enrichissement par sources externes » pèse 4 758 l. = 38 % de `Service/`, à plat (sauf `DataTourisme/`, `Wikidata/`). |
| 2 | Conventions | `final` 100 %, `readonly` 87 % ; 5 conventions pour la présentation (`Ecran`/`ViewBuilder`/`Provider`/`Reponse`/`Catalog`), `Catalog` vs `Catalogue`, `Manager` pour 4 rôles, module `Enrichment` (traduction) vs `Enrichissement*` (données externes) vs entités `FicheEnrichmentRun/Scan`. |
| 3 | Couplages | non déclarés dans ARCHITECTURE.md : Pim→Etl (6 fichiers, cycle avec Etl→Pim 32), Pim→Ocr (2), Pim→Dashboard (1), Pim→Audit (4), Pim→Vision (4, module absent du document). Figés dans `deptrac.baseline.yaml` (phase 1). |
| 4 | Duplication | 4 contrôleurs de gamme = 1 097 l. pour ~150 utiles ; 4 `*DocumentController`, 4 `*AdminManager`, 4 `*AdminViewBuilder`, 2 `*DocumentManager` (~75 % identiques) ; transport HTTP 429/lissage copié entre `GeoapifyClient` et `RechercheEntrepriseClient`. |
| 6 | Import | 2 pipelines parallèles (legacy impératif ×4 mappers ; import de masse déclaratif) + l'OCR qui réimplémente `applyFields/applyCollections`. |
| 7 | Code mort | méthodes : 4 × `*AdminViewBuilder::form()`, `ColumnKind::Time` + 2 branches, `RowConverter::splitList($raw, bool $uppercase)` toujours `false` ; 49 `use` fossiles dans les 4 contrôleurs de gamme. |

## Élevé

### E1. `FicheEditeurEcran` : 37 dépendances, 12 responsabilités, hub de 5 modules
`src/Pim/Service/FicheEditeurEcran.php` (≈ 820 l.), constructeur = 37 paramètres,
34 `use App\`, imports d'Account, Dam, Audit, Etl, Ocr. Responsabilités :
soumission partielle, sites de diffusion + géo, navigation/onglets/complétude par
section, en-tête + actions workflow, Salesforce, médias 4 gammes, collaborateurs,
extraction OCR, suggestions en attente, historique audit, fusion, icônes/slugs.
`FicheSectionsCatalogue::pour()` (non mémoïsé) appelé 3× par rendu.
**Proposition :** garder `variables()` comme assembleur et extraire 5 fournisseurs
de bloc (`EditeurNavigation`, `EditeurEntete`, `EditeurMedias`,
`EditeurSuggestionsAttente`, `EditeurExtraction` — seul à dépendre d'Ocr). 37 → ~8
dépendances. Vérification par comparaison HTML avant/après sur 4 fiches.

### E2. Quatre contrôleurs de gamme copiés-collés
`LieuController` (313), `ActiviteController` (280), `ServiceEvenementielController`
(279), `RestaurantController` (225) : 8 actions POST × 4 sur le même squelette
`find → 404 → deny → form → try/catch → redirect`. Libellés anglais hors Lieu,
flashes de succès seulement pour Lieu, 49 imports fossiles. Archive/delete
unifiés (phase 2). **Proposition :** `FicheTransitionExecutor::executer(transition,
entite, actor, ?reason): list<flash>` + `FicheWorkflowController` unique routé
`/referentiel/{gamme}/fiche/{id}/{transition}` (conserver les noms
`app_pim_{domaine}_{transition}` générés par `FicheActionFormFactory:30,105`).
0 test fonctionnel sur ces 32 routes : en écrire avant.

### E3. `AdminEventCatalog` importe 6 `MessageHandler` du DAM pour un catalogue de documentation (10 messages sur 21 : dérive). Référencer les `Message`, ou dériver de `#[AsMessageHandler]`.

## Moyen

### M1. Clients HTTP
`GeoapifyClient.php` (645 l.) : place-details + mapping tags OSM (l.61-101, 194-294,
table ~25 clés l.206-243), POI (116-154), routing (166-188), autocomplétion
(296-458), géocodage en lot (460-541, 613-644), transport lissé (550-603).
8 consommateurs typent la classe concrète ; `GeocodeurEtrangerInterface` n'est
utilisée que par `GeocodeurAdresses` et `VerifierLocalisationsCommand`.
`RechercheEntrepriseClient.php` (445 l.). Transport dupliqué mot pour mot :
`GeoapifyClient::executer/retryAfter` l.582-598 ≡ `RechercheEntrepriseClient`
l.312-331 ; variantes proches `Vision/OpenAiImageProvider.php:143`,
`Vision/OpenAiTextProvider.php:61`, `Ocr/BoxExtractProvider.php:109`.
Matching de nom : `GeoapifyClient::fluxEtablissements` l.364-380 ≈
`RechercheEntrepriseClient::correspondALaFiche` l.171-188 ; normalisation
`u()->trim()->lower()->ascii()` écrite 5× (`NomSimilarite.php:33` existe).
**Proposition :** `Shared\Http\ClientHttpLisse` ; scinder Geoapify en `GeoapifyHttp`
/ `OsmTagsExtracteur` / `GeoapifyPlaces` / `GeoapifyAutocomplete` /
`GeoapifyGeocodeur` ; DTO `SuggestionAdresse`. 5 tests couvrent les endpoints.

### M2. `EnrichissementSuggestionArbitre` (437 l., 9 dépendances → 6 après phase 5)
Dispatch par `str_starts_with` sur le code de champ (l.77-112), `appliquerLieu`
l.103-205, création de LOV à la volée (215-254), décodage payload (283-334). Codes
(`lieu_chaine`, `restaurant_types_cuisine`, `activite_plus`…) émis par 7 classes
sans enum. Doublons : garde « atouts déjà saisis » ×3, `assertFraicheur +
changeDescriptionGenerale` ×4, boucle `LovValeurResolution::codePour`
(`fusion()` l.391-406 ≡ `LieuAttributsVerifier::ajouterLov` 179-186 ≡
`RestaurantAttributsVerifier` 351-358). **Proposition :** enum `ChampSuggestion`,
`SuggestionApplierInterface` + 4 implémentations taguées, `SuggestionPayload`.

### M3. Familles jumelles
| Famille | Similarité | Proposition |
|---|---|---|
| 4 `*AdminManager` (112-115 l.) | Activite ≡ Service ~98 %, Restaurant ~85 %, Lieu ~60 % | `FicheAdminManager::save(union, form, existing, actor)` avec table `[champ form => (DocumentUsage, titre, source)]` par gamme ; écrire 4 tests de `save` avant |
| 4 `*AdminViewBuilder` (60-153 l.) | Activite ≡ Service 100 % hors préfixes ; `form()` morte dans les 4 | `DocumentsModalesVue::documents($entite, prefixeNom, prefixeRoute, metadataType, ?filtreUsage)` ; conserver noms de formulaires et `csrf_token_id` |
| `LieuDocumentManager` (176) / `FicheDocumentManager` (190) | ~75 % | un seul manager + `RessourceLieu::rattacherSalle(Salle\|RestaurantSalle\|null)` ; 0 test |
| `LieuPhotoManager` (200) | déjà multi-gammes | renommer `FichePhotoManager` |
| `LieuPhotoController` (190) / `GammePhotoController` (297) | copie (commit 9eca0b0) | étendre `GammePhotoController` à `lieux`, `LieuMediaCsrfGuard::assertRequest(Request, entite)`, supprimer `LieuPhotoController` + `LieuMediaModalesController` |
| 4 `*DocumentController` (129-169 l.) | Activite ≡ Service ~98 % | `FicheDocumentController` paramétré par un profil de gamme, étape 1 Activite+Service |
| 2 `*AttributsVerifier` (226/227 l.) | ~55 % | `SuggestionLovAssembleur` + `SuggestionsCommunesPlace` ; garder 2 tables (`CATEGORIE` 16, `CUISINE` 50) |
| `FicheLieuController` (85) ≡ `FicheGammeController` (97), `FicheMediasBlocController::lieu()/gamme()` | corps identiques | 1 contrôleur avec `requirements gamme = lieux\|…` |

### M5. Logique dans les contrôleurs (13 contrôleurs > 150 l.)
`ReferentielController::actions()` l.116-224 (109 l.) ; `exporter()` l.255 charge
tous les ids pour compter ; `exportFichier()` l.313-337 dépend d'un
`MessageHandler` (`GenererReferentielExportHandler::cle`, `::CONTENT_TYPE`) et de
`%env(S3_PREFIX)%`. `FicheController::adresseAutocomplete()` l.105-130 = un
service. `ReferentielFusionController::appliquer()` l.138-153. `RelanceCompletudeAdminController` : 4 formulaires définis 2×. `LovAdminController::edit()` l.141-146.
Dispatchs directs hors outbox : `ReferentielController.php:267-274`,
`FicheEnrichirController.php:53-54` (patron « `demarrer()` puis `dispatch` »),
`RelanceCompletudeAdminController.php:152` — stampés depuis la phase 3, mais la
trace « en file » resterait si le dispatch échouait.

### M7. Listes définies plusieurs fois
Codes d'actions groupées écrits 5× (`ReferentielActionGroupee::PLAFONDS` l.12-27,
`in_array` l.47, `match` voter l.60-68, `transition` l.200-208,
`ReferentielEcran::actions()` l.174-213) → enum `ActionGroupee`. `ReferentielEcran::LIBELLES`
miroir des `choices` de `ReferentielFiltresType.php:40-100`. `FicheSectionsCatalogue`
: sections « Booster / Utilisateurs / Templates » identiques ×3 (l.271-291 ≡
357-377 ≡ 436-456), « Médias » ×4 ; `pour()` non mémoïsé. Champs par gamme : 2
racines indépendantes pour Lieu (`Import/Schema/LieuImportSchema` ≈149 colonnes,
`Form/LieuFormCatalog` 100 champs, recouvrement ≈95 %) ; `CompletenessFieldCatalog.php:103-177`
réécrit Restaurant/Activité/Service à la main ; 14 capacités de salle listées dans
5 fichiers. Aucun test de cohérence schéma ↔ form catalog.

### M8. Import : 2 pipelines + OCR
Legacy (`Etl/Command/ImportLegacy*Command` → `LegacyCsvReader` → `Legacy*RowMapper`
422/278/206/175 l.) : localisation copiée ×4, `salles()` Lieu :304-342 ≡ Restaurant
:188-226 — jetable après go-live, ne pas refactorer. Import de masse déclaratif
(`FicheImportRowProcessor::applyFields/applyCollections` l.425-573), sain.
`Ocr/Service/OcrSuggestionApplier.php:127-175` réimplémente `applyFields/applyCollections`
→ extraire `SchemaFieldApplier` partagé.

## Faible

- Nommage : 5 conventions de présentation ; `Manager` pour 4 rôles (`SavedViewManager`
  31 l., `RelanceCompletudeAdminManager` 23 l. = `exclure()` + flush) ; suffixes FR
  concurrents (`Arbitre`, `Planificateur`, `Enregistreur`, `Exporteur`, `Fusionneur`,
  `Suggesteur`, `Completeur`, `Correcteur`, `Attribueur`, `Journal`) ; `Enrichment`
  ≠ `Enrichissement` ≠ `FicheEnrichmentRun`. `#[Autowire]` vs `services.yaml` pour
  le même type de valeur (`MAILER_FROM`, `S3_PREFIX`).
- Placement : `Completeness/` existe mais 6 classes de complétude dans `Service/` ;
  cluster enrichissement (4 758 l., 42 classes) à plat ; `AdminEventCatalog`/`AdminWorkflowCatalog`
  = données de documentation ; 4 `ReadModel/*ListItem` et 5 `*ListPage` identiques.
- Reliquats : `indexBloc($type, 'suggestions_attente')`
  (`EnrichissementSuggestionController.php:54`, `FicheAdresseSuggestionController.php:49`)
  et `urlExtraction()` cherchant le bloc `'suggestions'` alors qu'aucune section ne
  les déclare (repli silencieux sur la section 0) ; messages flash dupliqués
  (« Le motif du refus est obligatoire. » ×4…) ; 7 translittérations ASCII maison ;
  `FicheCreationManager::CONTACT_REPLI` l.36-40 = e-mail de production en dur.

## Ordre suggéré (rendement / risque)
1. M3 fusion Activite+Service (DocumentController, ViewBuilder, AdminManager) puis Restaurant — S, faible.
2. E2 `FicheTransitionExecutor` + contrôleur unique — M/L, moyen (0 test).
3. M1 `ClientHttpLisse` + découpage Geoapify — M, faible.
4. E1 découpage `FicheEditeurEcran` — M, faible.
5. M5 sortir la logique des contrôleurs — M, faible.
6. M2 `SuggestionApplier` par gamme + enum — M, moyen.
7. M7 racine unique des champs par gamme — L, après go-live.
