# Import legacy — CSV production → PIM

Reprise des données de production (`lists_infos_produits_v2_*.csv`, 91 colonnes, ~26 600 lignes) dans une base PIM dédiée (`mdm_reel`). Commandes manuelles, idempotentes, relançables :

| Commande | Rôle | Périmètre |
|---|---|---|
| `app:legacy:import-lieux` | Fiches Lieu | Gamme ∈ {Hôtel, Lieu, Centre de congrès} (~19 835) |
| `app:legacy:import-activites` | Fiches Activité | Gamme = Idée (~2 304) |
| `app:legacy:import-services` | Fiches Service | Gamme = Prestataires de service (~1 534) |
| `app:legacy:import-restaurants` | Fiches Restaurant | Gamme = Restaurant (~2 918) |
| `app:legacy:import-photos` | Photos + variantes WebP | Toutes les fiches importées |
| `app:legacy:import-translations` | Traductions (6 locales) | Lues dans le **dump SQL** de production (rejouable en prod au déploiement) |
| `app:legacy:import-collaborateurs` | Collaborateurs + statut Business Premium | Lus dans le **XLSX** `listes_fiches_produits_*.xlsx` (~27 000 lignes) |

**Pivot** : `pim_fiche.code` = « Id syspad » du CSV (fourni à l'insert, le trigger n'attribue le compteur qu'aux fiches créées sans code). La table `etl_legacy_fiche` (syspad ↔ fiche ULID, gamme, photos_json) porte l'idempotence ; `etl_legacy_photo` suit chaque photo (statuts `pending/done/error/missing_file/invalid/skipped_limit`).

## Sources et préfixe S3

- **CSV + dump SQL** : `var/tmp/import/` du projet (chemins par défaut des `--file`). En dev, le docker-compose parent y monte le dossier hôte en lecture seule ; en prod, y déposer les fichiers le jour du déploiement.
- **Images originales** : dossier `tmp/` **à la racine du bucket S3 privé** (source par défaut de `app:legacy:import-photos`, option `--images-s3-prefix`). L'option `--images-dir` bascule sur une copie locale (montée sur `/var/legacy-images` en dev).
- **Préfixe des médias** : tous les chemins S3 (imports, uploads via les fiches, documents) suivent `{S3_PREFIX}/{type d'entité}/…`. `S3_PREFIX` vaut `dev` par défaut ; en préprod/prod, définir la valeur de l'environnement (variable Upsun). Ne jamais surcharger le préfixe à la main dans les commandes.

## Procédure de déploiement

```bash
# 1. Déposer le CSV et le dump SQL dans var/tmp/import/
#    (lists_infos_produits_v2_06-08-2026_02H24.csv, dump-production.sql)
# 2. Téléverser les images originales sous tmp/ à la racine du bucket privé
#    (mêmes chemins relatifs que photos_json, ex. tmp/<slug>/facade/x.jpg)
php bin/console doctrine:migrations:migrate -n        # 3. schéma + LOV + triggers
php bin/console app:legacy:import-lieux               # 4. fiches, par entité
php bin/console app:legacy:import-activites
php bin/console app:legacy:import-services
php bin/console app:legacy:import-restaurants
php bin/console app:legacy:import-photos --seed-only  # 5a. semis du suivi, UNE seule fois
php bin/console app:legacy:import-photos --shard=0/4  # 5b. ×4 terminaux (0/4 … 3/4), aucun semis
php bin/console app:legacy:import-photos --retry-errors   # reprise si besoin
php bin/console app:legacy:import-translations        # 6. traductions (dump SQL)
# 7. Nettoyage : supprimer var/tmp/import/ et le dossier tmp/ du bucket privé
#    (déposer d'abord le XLSX de l'étape 8 s'il n'y est pas encore)
php bin/console app:legacy:import-collaborateurs      # 8. collaborateurs + Business Premium (XLSX)
```

Chaque import accepte `--dry-run` (répétition sans écriture ; pour les photos en mode S3, seule la présence des objets est contrôlée). En local, cibler la base réelle sans toucher `.env.local` :

```bash
DBURL='mysql://mdm:<mdp>@sql:3306/mdm_reel?serverVersion=11.4.0-MariaDB&charset=utf8mb4'
run() { docker compose exec -e DATABASE_URL="$DBURL" -e APP_DEBUG=0 php php bin/console "$@"; }
run app:legacy:import-photos --images-dir=/var/legacy-images --dry-run   # contrôle complet sur la copie locale
```

Options communes des imports de fiches : `--file`, `--dry-run`, `--limit`, `--from`, `--batch-size`, `--only-syspad`. Statut : publié CSV `true` → fiche publiée (`publishForImport`), sinon brouillon. Les valeurs non mappables produisent des **warnings agrégés** (jamais d'échec de ligne) ; seuls un Id syspad ou un nom invalides rejettent une ligne.

## Mapping Lieu (`LegacyLieuRowMapper`)

| Colonne CSV | Cible PIM | Transformation / remarques |
|---|---|---|
| Id syspad | `Fiche.code` + `etl_legacy_fiche.syspad_id` | obligatoire, numérique |
| Publié / non publié | statut fiche | `true` → publiée, sinon brouillon |
| Nom Français | `Fiche.label` | obligatoire, tronqué 255 |
| Gamme | `Lieu.generaleGammeLibelle` | + filtre de périmètre |
| Classification (★…/Palace) | `generaleTypologie` | ★★→`GENERALE_TYPOLOGIE_1` … ★★★★★→`_4`, Palace→`_5` |
| Type de lieux (JSON) | `generaleTypologie` | libellés du catalogue + alias : Kartings→`_21`, Résidence/Appart'hotel→`_13`, Salle / Bureau→`_38`, Lieu avec incentive intégré→`_40` ; « Avec Hébergements » → `chambreHebergement(true)` ; inconnu → `_40` + warning |
| Thématique (JSON) | `taCadreEnv` / `taThematique` | Mer/Au vert/Campagne/Montagne/Lac/Centre Ville → cadre env ; Bien-être/Golf/Eco-responsable/RSE/Gastronomique/Oenotourisme/Châteaux → thématique ; « Pas de Thème », « Ile », « Esat » ignorés |
| Nombre de chambres / twin / single / capacité | `chambreNbTotal/NbTotalTwin/NbTotalSingle/CapaciteTotale` | `0`/vide → null ; total > 0 → `chambreHebergement(true)` |
| Nombre / capacités / surface salles agrégées | `salleReunion{NbTotal, CapaciteMaxCocktail, CapaciteMaxTheatre, SurfaceMaxReunion, Exist}` | `Exist` = nb > 0 |
| Pays…Ville, Latitude/Longitude | `Localisation` | code pays ISO-2 déduit (table statique, inconnu → warning) ; GPS invalide → warning, formaté 7 décimales |
| Ligne/Arret RER et Metro | `AccesLieu` type `Metro` | nom « [RER ]Ligne – Arrêt », `modeTransport` RER/Métro |
| Aéroports 1-2, Gare 1, Ville + distances | `AccesLieu` types `Aeroport`/`Gare`/`GrandeVille` | distance si numérique > 0 |
| Salle (JSON) | entités `Salle` | nom, superficie, capacités (réunion/U/Grande Ecole→classe/théâtre/cabaret/banquet/cocktail/auditorium), lumière du jour, PMR, dansant ; capacité `0`/vide → null |
| Description générale | `descGenerale` | **tronqué 1 000** + warning (limite « Bible » ; texte intégral récupérable via bp-dump) |
| Hébergement | `chambreDescGenerale` | tronqué 1 000 + warning |
| Salles de séminaires | `salleReunionDescSalleSeminaire` | non tronqué (TEXT) |
| Loisirs 1-8 (+ Court de tennis, Parcours de golf) | `loisirInterne` | liste dédupliquée |
| Les plus 1-5 | `atout1..5` | **tronqué 35** + warning |
| Journée d'étude / Séminaire résidentiel | `tarification().seminaireJourneeJourneeEtude` / `seminaireNuiteeResidentiel` | `0`/vide → non renseigné |
| Wifi, Fibre, Clim salles | `techniqueReunion` | `TECHNIQUE_REUNION_1/_2/_10` |
| Piscines, Spa, Sauna, Hammam, Jacuzzi, Remise en forme, Fitness | `bienEtre` | `BIEN_ETRE_2..9` |
| Climatisation en chambre | `equipements` | `EQUIPEMENTS_5` |
| Accès PMR | `pmrAcces` | booléen |
| Photos (JSON) | `etl_legacy_fiche.photos_json` | traité par `app:legacy:import-photos` |

**Colonnes ignorées (Lieu)** : Restauration / Gastronomie (aucun champ description restauration sur `pim_lieu` — même lacune que les traductions `restaurationFr`), Offre spéciale + dates promotion (pas de champ cible), Téléphone (seul un téléphone de facturation existe côté administratif), Lien youtube (champ vidéo inexistant sur Lieu), Plan des salles, Le mot de l'expert, colonnes activité/prestataire (80-86, 88-91).

## Mapping Activité (`LegacyActiviteRowMapper`)

| Colonne CSV | Cible PIM | Transformation / remarques |
|---|---|---|
| Id syspad | `Fiche.code` + pivot | idem Lieu |
| Publié / non publié | statut fiche | idem Lieu |
| Nom Français | `Fiche.label` | tronqué 255 |
| Type d'activités (JSON, multi) | `thematiques` (**multi**) | tous les types mappables sont conservés : Sportives→`TA_SPORTIVE_LUDIQUE`, Sensations Fortes & Sports Mécaniques / Aériennes→`TA_SENSATION_SPORT_MECA`, Nautiques→`TA_NAUTIQUE_AQUATIQUE`, Culinaires & Oenologiques→`TA_CULINAIRE_OENOLOGIQUE`, Créatives…→`TA_CREATIVE_ARTISTIQUE_MUSICALE`, Culturelles & Découvertes→`TA_CULTURELLE_REFLEXION_DECOUVERTE`, Nature / RSE→`TA_NATURE_RSE`, Détentes→`TA_BIEN_ETRE_DETENTE`, Digitales High Tech→`TA_DIGITAL_HIGH_TECH` ; **« Insolites » sans équivalent** → warning |
| — | `sousThematiques` (multi, LOV `SOUS_THEMATIQUE_ACTIVITE`, 64 valeurs Bible) | **aucune donnée legacy** : à renseigner à la main dans l'admin (cases affichées selon les thématiques cochées) |
| Objectifs de l'activité (texte multiligne) | `objectifs` | Cohésion d'équipe/Fédérer/Intégration→`OBJECTIF_SEMINAIRE_1`, Communiquer→`_2`, Motiver→`_3`, Sensibiliser→`_5`, Récompenser/Fidéliser→`_6`, Animer/Challenger/Stimuler→`_8` ; libellé inconnu → warning |
| Description générale | `descriptionGenerale` | non tronqué |
| Lien youtube | `youtubeUrl` | tronqué 255 |
| Participants min/max | `participantsMin/Max` | `0`/vide → null |
| Temps minimum/maximum (`H:MM`) | `dureeMinMinutes/dureeMaxMinutes` | converti en minutes, `0:00` → null |
| Tarifs activité à partir de | `tarifParPersonne` | `0`/vide → null |
| Les plus 1-5 | `plus` | **max 4** (limite entité) + warning si 5ᵉ |
| Rayon d'action (Région) / (département) | mode `Mobile` + `touteFrance`/`regionsMobiles`/`departementsMobiles` | listes multilignes ; « Toute la France » → `touteFrance(true)` |
| (sinon) Ville renseignée | mode `Fixe` | — |
| (sinon) | mode null | warning `mode_intervention_indetermine` |
| Pays…Ville, GPS | `Localisation` | idem Lieu |
| Photos (JSON) | `etl_legacy_fiche.photos_json` | plafond 10 (cf. ci-dessous) |

**Colonnes ignorées (Activité)** : prestataire (obligatoire seulement à la soumission, non présent dans le CSV), langues, engagements RSE, offres (pas de données CSV), colonnes hôtelières.

## Mapping Service (`LegacyServiceRowMapper`)

| Colonne CSV | Cible PIM | Transformation / remarques |
|---|---|---|
| Id syspad / Publié / Nom Français | `Fiche.code` + pivot / statut / `Fiche.label` | idem Lieu |
| Type de prestataire | `prestations` (LOV `TYPE_PRESTATAIRE`) | alias : Traiteurs→`TS_TRAITEUR`, Transporteurs→`TS_TRANSPORT_LOGISTIQUE`, Animations Evènementielles / Photographes→`TS_ANIMATION_ARTISTE`, Goodies→`TS_CADEAU_CLIENT_GOODIE`, Réalisations audiovisuelles / Techniques-Sonorisations→`TS_SON_VIDEO`, Location mobiliers / Fleuristes-Décorations / Constructions éphémères→`TS_TECHNIQUE_AUDIOVISUEL`, Traductions-Interprètes→`TS_TRADUCTION_INTERPRETARIAT`, Accueil et sécurité→`TS_ACCUEIL_SECURITE`, Imprimeurs / Communications-Pub / Signalétiques→`TS_COMMUNICATION_PUBLICITÉ`, Apps et sites web→`TS_DIGITAL_HYBRIDE` ; vide (404 lignes) → aucune prestation + warning |
| Categorie | `demarcheRse(true)` si « RSE », `prestataireEsat(true)` si « ESAT / STPA » | sinon null |
| Description générale | `descriptionGenerale` | non tronqué |
| Lien youtube | `youtubeUrl` | tronqué 255 |
| Tarifs activité à partir de | `tarifParPrestation` | `0`/vide → null ; les 4 autres tarifs restent null |
| Rayons d'action | mode `Mobile` + `regionsMobiles`/`departementsMobiles` | pas de flag « toute la France » sur les services : la valeur est conservée telle quelle dans les régions ; sinon Ville → `Fixe` |
| Pays…Ville, GPS | `Localisation` | idem Lieu |
| Photos (JSON) | `photos_json` du pivot | plafond 10, usages PRINCIPALE/DIVERSE |

**Colonnes ignorées (Service)** : Les plus 1-5 (pas d'atouts sur les services — warning), Téléphone, Objectifs, Tag. Booléens d'adaptabilité, matériel, sur devis et les 4 autres tarifs : aucune donnée CSV → null, à compléter dans l'admin.

## Mapping Restaurant (`LegacyRestaurantRowMapper`)

| Colonne CSV | Cible PIM | Transformation / remarques |
|---|---|---|
| Id syspad / Publié / Nom Français | `Fiche.code` + pivot / statut / `Fiche.label` | idem Lieu |
| Thématique (JSON) | `typesRestaurant` / `engagementsRse` | Gastronomique→`GASTRONOMIQUE`, Mer→`BORD_DE_MER`, Au vert→`AU_VERT`, Lac→`BORD_EAU`, Montagne→`RESTAURANT_ALTITUDE` ; Esat→RSE `ESAT` ; autres (Oenotourisme, RSE, Eco-responsable…) → warning |
| Description générale + Restauration / Gastronomie | `descriptionGenerale` | concaténées (double saut de ligne) — un seul champ cible |
| Les plus 1-5 | `atouts` | max 5 (255 car. chacun, tronqué + warning) |
| Capacité cocktail plus grande salle | `capaciteCocktail` | `0`/vide → null |
| Wifi / Clim salles | `equipements` (`WIFI`, `CLIMATISATION`) | booléens |
| Accès PMR | `accesPmr` | booléen |
| Salle (JSON) | entités `RestaurantSalle` | mêmes clés que Lieu |
| Accès (aéroports/gare/ville/RER-métro) | `RestaurantAcces` | type + nom (pas de champ distance sur l'entité) |
| Pays…Ville, GPS | `Localisation` | idem Lieu |
| Lien youtube | `youtubeUrl` | tronqué 255 |
| Photos (JSON) | `photos_json` du pivot | plafond 10, usages PRINCIPALE/DIVERSE |

**Colonnes ignorées (Restaurant)** : Classification ★ (8 lignes, étoiles hôtelières), Salles de séminaires (texte — pas de champ cible + warning), Hébergement (532 restaurants d'hôtels — l'hébergement est porté par la fiche Lieu), colonnes chambres/tarifs séminaire, Téléphone. Types de cuisine, jours d'ouverture, horaires, privatisation, services : aucune donnée CSV → à compléter dans l'admin.

## Photos (`app:legacy:import-photos`)

Sources : chemins relatifs du JSON « Photos », fichiers dans `/var/legacy-images`. Original → S3 privé, 7 variantes WebP générées **inline** (`MediaProcessingService`, pas d'outbox) → S3 public. Validations : ≥ 960×480, ≤ 25 Mo, JPEG/PNG/WebP.

| Catégorie legacy | Usage (fiches Lieu) | Usage (fiches Activité) |
|---|---|---|
| master (1ʳᵉ) | PHOTO_PRINCIPALE | PHOTO_PRINCIPALE |
| master (suivantes) | PHOTO_DIVERSE | PHOTO_DIVERSE |
| facade | PHOTO_FACADE | PHOTO_DIVERSE |
| chambre | PHOTO_CHAMBRE | PHOTO_DIVERSE |
| restaurant | PHOTO_RESTAURATION | PHOTO_DIVERSE |
| salles_reunion / divers | PHOTO_DIVERSE | PHOTO_DIVERSE |

Plafonds : **25 photos par lieu**, **10 par activité** (invariants admin/validation) — le surplus est tracé `skipped_limit`. Fichiers absents de la copie locale → `missing_file` (relançables après synchronisation du dossier images). Suivi : `SELECT status, COUNT(*) FROM etl_legacy_photo GROUP BY status;`

## Traductions (`app:legacy:import-translations`)

Source : le **dump SQL** de production (`--file`, défaut `var/tmp/import/dump-production.sql`) — parsé en streaming (`LegacySqlDumpReader`), aucune base annexe requise : la commande est rejouable telle quelle en production le jour du déploiement, il suffit de rendre le fichier dump accessible. Options : `--dry-run`, `--limit`, `--locale=xx`, `--batch-size`.

**Clé de jointure** : `bp_produit.syspad_id` = code fiche (⚠ pas `bp_produit.id`). Mapping :

| Source legacy | Cible (`enrichment_fiche_translation.field_path`) |
|---|---|
| `i18n_translation_produit.descriptionFr` | `lieu.descGenerale` / `activite.descriptionGenerale` / `restaurant.descriptionGenerale` / `service.descriptionGenerale` selon le type de fiche |
| `i18n_translation_lieu.chambresFr` | `lieu.chambreDescGenerale` |
| `i18n_translation_lieu.sallesFr` | `lieu.salleReunionDescSalleSeminaire` |
| `restaurationFr`, `offreSpecialeFr` | non mappés (pas de champ cible — comptés dans le rapport) |

**Règle de cohérence** (`LegacyTranslationRule`) : la traduction est importée **disponible** (origin `manual`) si le français actuel du PIM est identique au français legacy (ou à sa troncature d'import à 1 000 caractères) ; si le français a divergé, elle est importée **obsolète** — traduction conservée, re-validation humaine dans l'admin. Source PIM vide → ignorée. Idempotent : les couples (fiche, champ, locale) existants sont ignorés. Aucun appel Google.

**Atouts et loisirs** : `bp_atout` / `bp_loisir` sont des référentiels partagés (pas de rattachement direct au produit dans les i18n) — la commande construit un dictionnaire *texte français → traductions* et l'applique aux champs libres du PIM : `lieu.atout1..5`, `lieu.loisirInterne[i]`, `activite.plus[i]`, `restaurant.atouts[i]`. Les variantes de troncature de l'import des fiches sont prises en compte (35 caractères + ellipse pour les atouts lieux, 255 pour restaurants/activités). ~497 000 traductions appariées.

**Libellés de LOV** : les référentiels `bp_theme`, `bp_type_lieu`, `bp_type_prestataire`, `bp_type_activite`, `bp_objectif`, `bp_equipement` alimentent `pim_attribute_value_translation` par correspondance exacte de libellé français (~240 libellés traduits ; les libellés PIM renommés par la Bible ne matchent pas et restent à traduire).

## Collaborateurs et Business Premium (`app:legacy:import-collaborateurs`)

Source : le **XLSX** `listes_fiches_produits_*.xlsx` (`--file`, défaut `var/tmp/import/listes_fiches_produits_06-08-2026_17H31.xlsx`), lu nativement via openspout — ne pas convertir en CSV. Une ligne = une fiche (« ID Syspad » = `pim_fiche.code`) ; les collaborateurs sont empilés **dans les cellules** (une entrée par retour-ligne) sur trois colonnes parallèles « Identifiant email » / « Nom » / « Prénom ».

⚠ **Alignement par index** : les trois listes sont appariées position par position et contiennent des entrées **vides** quand une information manque (ex. un email sans nom ni prénom). Elles ne doivent jamais être filtrées de leurs vides avant appariement, sous peine de mélanger les identités — le parseur conserve les trous.

Écritures :
- `FicheCollaborateur` (unique par email, normalisé minuscules) + `FicheAffiliation` (unique par couple collaborateur × fiche ; rôle `--role`, défaut `utilisateur` ; auteur `--created-by`, défaut premier super admin actif). Aucune invitation marketplace n'est envoyée (création directe, hors `FicheAffiliationManager::invite`).
- Colonne « Adhérent Business Premium » (valeur `Adhérent`) → `Fiche.businessPremium`, appliqué sous `preserveWorkflowDuring` (aucune transition de workflow, les fiches publiées le restent).

Ignorés (comptés dans le rapport) : entrées nom/prénom **sans email** (l'email est la clé), emails invalides, Id syspad sans fiche PIM, doublons d'email au sein d'une même cellule. Pas de colonne téléphone dans le fichier. Options : `--dry-run`, `--limit`, `--only-syspad`, `--batch-size`. À lancer **après** les imports de fiches (jointure par code syspad). Gros volume : exécuter avec `APP_DEBUG=0` (et `php -d memory_limit=1536M` au besoin) — le profiler dev accumule les requêtes SQL.

Bilan `mdm_reel` (2026-08-11) : 19 467 collaborateurs, 25 272 affiliations, 491 fiches Business Premium ; 418 syspad inconnus, 238 emails invalides.
