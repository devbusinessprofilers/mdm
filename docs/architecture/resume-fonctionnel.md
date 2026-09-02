# Résumé fonctionnel du MDM (PIM / DAM)

Application centrale de gestion des fiches établissements et prestataires :
saisie, validation, enrichissement, puis diffusion vers la marketplace et
les sites partenaires.

## Les types de fiches

**Lieu** — Fiche d'un établissement (hôtel, centre de congrès, lieu
événementiel) : description, gamme, chambres, salles de réunion, accès,
tarifs, équipements. L'amplitude d'ouverture affichée est dérivée des
horaires par jour.

**Restaurant** — Fiche de restauration, autonome ou rattachée à un lieu
(liaison une fiche ↔ une fiche, éditable des deux côtés) : cuisine,
privatisation, horaires saisis jour par jour, capacités, salles, accès.

**Activité** — Fiche d'une offre événementielle (team-building, séminaire,
incentive) : thématiques, objectifs, nombre de participants, durée, tarifs.

**Service événementiel** — Fiche d'un prestataire (traiteur, transporteur,
photographe, animateur…) : types de prestation, couverture géographique,
tarifs, engagement RSE.

**Localisation** — Adresse de la fiche, normalisée sur le référentiel
officiel INSEE (régions/départements) et vérifiable via la Base Adresse
Nationale (GPS, correction de code postal et de ville).

**Utilisateur (contact prestataire)** — Contact rattaché à une ou plusieurs
fiches, avec un rôle (manager, administrateur, utilisateur) et des droits par
fiche (traite les demandes, les contenus, les paiements). À ne pas confondre
avec les « Collaborateurs », les comptes internes du PIM.

**Site de diffusion** — Canal sur lequel une fiche peut être diffusée
(marketplace, sites thématiques…), avec ses caractéristiques (obligatoire,
payant, groupe). Des critères géographiques (multi-zones) attribuent
automatiquement les sites pertinents à la création d'une fiche.

## Le cycle de vie d'une fiche (workflow)

**En cours** — Brouillon : la fiche est créée et remplie par un éditeur, ou
renvoyée par un validateur avec un motif de refus.

**En attente de validation** — La fiche a été soumise par l'éditeur ; elle
attend la relecture d'un validateur.

**Validée** — Le validateur a approuvé la fiche ; elle est prête à être
publiée.

**Publiée** — La fiche est diffusée : elle part vers la marketplace et ses
traductions sont planifiées automatiquement.

**Archivée** — La fiche est retirée de la diffusion ; ses documents publiés
sont révoqués. Une fiche absorbée par une fusion est archivée et affichée
« Fusionnée ».

**Refus de validation** — Le validateur peut renvoyer une fiche soumise vers
« En cours » en indiquant un motif, visible par l'éditeur.

## Les rôles utilisateurs

**Éditeur** — Crée et remplit les fiches, puis les soumet à validation. Son
espace de travail montre ses fiches assignées, ses soumissions en attente et
ses priorités de complétude.

**Validateur** — Valide, publie, archive ou refuse les fiches ; peut les
modifier sans les repasser en brouillon ; consulte l'historique des
modifications. Son espace de travail ajoute la file de validation globale
(les soumissions les plus anciennes en premier) et les suggestions à
arbitrer.

**Administrateur** — Accès à tout l'espace d'administration : paramètres,
listes de valeurs, utilisateurs, collaborateurs, relances, supervision. Son
espace de travail ajoute l'intégrité du référentiel : fiches publiées sans
photo, droits d'image invalides, écarts de forme, suggestions en attente.

**Super administrateur** — Gestion des comptes administrateurs ; seul rôle à
voir l'accès à l'administration dans le menu profil.

Espace de travail par rôle (décision du 17 août 2026) : les vues de l'espace
de travail suivent exactement ces rôles — chacun n'accède qu'aux vues que
son rôle couvre (un validateur voit Éditeur et Validateur, un administrateur
voit les trois), la plus large s'ouvrant par défaut. Le profil « Chef de
projet » de la maquette (consultation, favoris, signalements) n'existe pas
dans le PIM : sans équivalent dans ces rôles ni mécanisme applicatif, il
relève de Salesforce — ou de l'extranet prestataire — s'il doit exister un
jour.

## Gestion quotidienne des fiches

**Référentiel** — Listes des fiches par type (lieux, restaurants, activités,
services) avec filtres (statut, complétude, pays…), tri (dont un tri par
pertinence quand une recherche est saisie), vues enregistrées et actions
groupées : soumettre à validation, valider, publier (les fiches non
conformes photos sont ignorées), archiver, ajouter un canal de diffusion,
fusionner, exporter.

**Recherche globale** — Moteur de recherche plein texte sur toutes les
fiches (code, nom, descriptions) depuis la barre de navigation, avec
autocomplétion et tolérance aux fautes de frappe (jusqu'à deux).

**Création de fiche** — Tunnel de création où seuls la gamme et le nom sont
obligatoires : recherche du pays puis autocomplétion de l'adresse (annuaire
des entreprises pour la France, Geoapify pour le monde) qui pré-remplit
adresse, GPS et informations légales (SIREN, SIRET, TVA), avec détection des
doublons.

**Édition rapide** — Modification allégée des champs simples sans passer
par le formulaire complet.

**Fusion de fiches** — Action de masse sur deux doublons de la même gamme :
écran comparatif champ à champ (le plus récemment audité d'abord), unions
dédoublonnées des listes, la fiche absorbée est archivée et pointe vers la
fiche conservée.

**Export et import Excel** — Export du référentiel en classeur Excel
(colonnes choisies par onglet, libellés lisibles, listes déroulantes),
persisté et partageable par lien pendant 30 jours ; import en masse au même
format (mise à jour écrasante par code, création sans code, libellés de
listes résolus automatiquement) avec rapport d'erreurs détaillé.

**Utilisateurs de la fiche** — Rattachement des contacts prestataires à une
fiche, attribution des rôles et des droits (demandes, contenus, paiements).

**Suggestions en attente** — Dans chaque fiche, un tableau liste les
corrections proposées par les vérifications automatiques et les sources
d'enrichissement, avec la source, la valeur actuelle, la proposition et le
score de confiance. Le validateur accepte ou ignore en un clic ; l'écran
Qualité offre la même chose en masse, par source, avec sélection multiple.

**Historique (audit)** — Journal complet de toutes les modifications de
chaque fiche, consultable par les validateurs et administrateurs.

## Médiathèque (DAM)

**Bibliothèque de médias** — Dépôt des photos et documents, validation des
métadonnées, génération automatique des formats web (WebP) publiés sur le
CDN.

**Onglet Médias de la fiche** — Organisé en onglets internes : Photos,
Plans, Supports commerciaux, Vidéo, Documents. La galerie se réordonne par
glisser-déposer ; **la photo principale est simplement la première de la
liste**. Le lien vidéo (10 hébergeurs autorisés, YouTube, Vimeo…) se gère
dans l'onglet Vidéo.

**Recadrage visuel** — Recadrage et rotation des photos dans une modale
interactive (ratio verrouillé, taille minimale garantie), sans écrasement de
l'original.

**Retouche et reconnaissance d'images** — Deux moteurs de retouche au
choix : OpenAI ou un traitement local ImageMagick (gratuit). La
reconnaissance automatique (légende, mots-clés, type de vue) via OpenAI est
disponible mais déclenchée manuellement.

**Droits et consentements** — Suivi des droits d'utilisation des images,
avec alerte avant échéance et expiration.

**Détection de doublons** — Signalement des images identiques ou
visuellement similaires, et des textes quasi identiques entre fiches
(copier-coller).

**Documents** — Les documents restent privés après dépôt ; leur publication
ou révocation est une action explicite, indépendante du statut de la fiche.

## Complétude et relances

**Score de complétude** — Chaque fiche reçoit un score global et par canal
de diffusion, calculé à partir de champs pondérés configurables (présence ou
longueur du contenu). Le score est recalculé à chaque sauvegarde.

**Configuration de la complétude** — L'administrateur définit les champs
pris en compte, leur poids et les canaux concernés, par type de fiche.

**Obligations photos** — Un minimum de photos conditionne la publication
(4 pour un Lieu, 1 ailleurs) ; une fiche publiée qui passe sous le seuil est
retirée de la marketplace.

**Relances de complétude** — Chaque lundi, un lot de relances est préparé à
8 h (fiches incomplètes et destinataires), vérifiable par l'administrateur,
puis envoyé par email à 14 h (automatiquement ou manuellement).

## Enrichissement et récupération de données externes

**Recherche d'entreprise** — À la création d'une fiche, l'annuaire public
des entreprises (recherche-entreprises.api.gouv.fr) pré-remplit
automatiquement les informations légales : SIREN, SIRET, numéro de TVA,
adresse du siège et coordonnées GPS.

**Vérification d'adresse (BAN et Geoapify)** — À chaque modification
d'adresse, un géocodeur vérifie automatiquement l'adresse saisie : la Base
Adresse Nationale (service public gratuit, sans clé) pour la France,
Geoapify (données OpenStreetMap, mondial) pour l'étranger. Les compléments
sûrs (GPS manquant, code postal ou ville vides) sont appliqués
automatiquement ; les écarts deviennent des suggestions à arbitrer en un
clic. Une adresse divergente n'est jamais écrasée sans décision humaine.

**Suggestions d'enrichissement multi-sources** — Des sources gratuites
proposent des compléments, chacune activable séparément dans
l'administration : Sirene (statut administratif de l'établissement), Atout France
(classement en étoiles, nombre de chambres), Geoapify/OpenStreetMap
(attributs, horaires, typologie), DATAtourisme, Wikidata (chaîne et groupe
hôteliers), et l'IA OpenAI (descriptions et atouts manquants). Un bouton
« Enrichir ce qui manque » sur la fiche interroge toutes les sources actives
en une fois ; chaque proposition reste une suggestion à arbitrer, rien ne
s'applique seul.

**Suggestion des accès** — Un bouton pré-remplit les accès d'un Lieu ou d'un
Restaurant (aéroports, gares, métro, grandes villes) à partir de référentiels
mondiaux embarqués et des itinéraires Geoapify.

**Pilule « Suggérer »** — Sur les champs de description, une proposition de
texte IA remplaçable et annulable, générée à la demande.

**Extraction de documents (OCR)** — Depuis une fiche, dépôt d'un PDF
(plaquette, grille tarifaire…) lu automatiquement : les valeurs détectées
sont proposées champ par champ avec leur niveau de confiance, puis validées
ou corrigées par un validateur avant d'être appliquées. Désactivé par
défaut, en attente du compte Box.

**Traduction automatique** — Les contenus des fiches publiées sont traduits
automatiquement en 6 langues par l'API Google Translate, puis corrigeables
manuellement ; seuls les textes modifiés sont retraduits. Un bouton permet
de relancer les traductions d'une fiche, y compris non publiée.

## Diffusion et synchronisations

**Synchronisation marketplace** — Toute fiche publiée est poussée
automatiquement vers la marketplace avec l'ensemble de ses données et de ses
photos ; la marketplace ne modifie jamais le PIM.

**Listes de valeurs (LOV)** — Les vocabulaires métier (typologies,
thématiques, équipements, prestations…) sont gérés dans le PIM et
synchronisés vers la marketplace.

**Visibilité géographique automatique** — À la création d'une fiche, les
sites de diffusion dont les critères géographiques correspondent (pays,
région, département, ou ville avec rayon en km) sont attribués
automatiquement ; un bouton et une commande permettent de rejouer
l'attribution sur le stock.

**Salesforce** — Chaque nuit à 3 h, les données commerciales Salesforce
(statut partenaire, évaluation client, RSE, contrats…) sont rapatriées dans
les fiches ; un webhook entrant permet à Salesforce de notifier un
changement pour un rafraîchissement immédiat. Dans l'autre sens, les fiches
partent vers Salesforce par e-mails CSV groupés (système de transition,
désactivé par défaut).

**API sites externes** — Les sites partenaires autorisés peuvent lire et
modifier certaines données de fiches via une API sécurisée (JWT), sans
jamais changer leur statut de validation.

## Administration et supervision

**Paramètres applicatifs** — Réglages métier modifiables directement depuis
l'interface d'administration, sans redéploiement (voir
[configuration.md](../exploitation/configuration.md)).

**Gestion des comptes** — Création des comptes internes (« Collaborateurs »),
attribution des rôles ; annuaire des contacts prestataires
(« Utilisateurs ») et de leurs rattachements aux fiches, avec invitation
vers la marketplace.

**Sites de diffusion** — Administration du référentiel des canaux de
diffusion et de leurs critères géographiques.

**Tableau de bord** — Statistiques générales, indicateurs de qualité des
données (adresses douteuses, photos manquantes, caractères restant à
traduire par langue), outils de lancement manuel des traitements.

**Journal des traitements** — La page Outils regroupe l'historique de tous
les traitements par famille : traductions, médias, enrichissements, exports,
imports, visibilité géographique, synchronisation marketplace, envois
Salesforce — avec relance possible des traitements en échec.

**Supervision technique** — Une page dédiée suit la charge des workers en
temps réel (battements de cœur, graphiques) et les messages en échec avec
relance individuelle.

**Écran Qualité** — Vue transverse de la qualité des données : santé par
gamme, suggestions à arbitrer par source (adresses, enrichissements,
documents), doublons de textes, fiches partageant la même adresse, écarts de
forme, notifications et décisions d'arbitrage.

## Reprise de données

**Import legacy** — Reprise complète de l'ancien système : environ 26 600
fiches (lieux, activités, services, restaurants), leurs photos, leurs
traductions et les collaborateurs, via des imports rejouables sans doublon
(voir [import-legacy.md](import-legacy.md)).

**Normalisation des adresses** — Remise en conformité des adresses
existantes sur le référentiel INSEE et vérification en masse via la Base
Adresse Nationale (rapport puis application non destructive).

## Traitements automatiques

### Au fil de l'eau (déclenchés par les événements)

Toute mutation de fiche converge vers un même point d'entrée asynchrone qui
enchaîne les traitements ci-dessous ; les appels externes lourds sont traités
par les workers, jamais dans la requête web.

| Déclencheur | Traitement | Paramètre / gate |
|---|---|---|
| Sauvegarde d'une fiche | Recalcul de la complétude (globale et par canal) et réindexation pour la recherche | configuration de la complétude |
| Sauvegarde d'une fiche | Garde photos : une fiche publiée passant sous le minimum repasse « En cours » et est retirée de la marketplace | `photos.min_lieu` (4), `photos.min_autres` (1) |
| Sauvegarde d'une fiche | Planification de la synchro marketplace : envoi, retrait ou purge des photos selon le statut et la diffusion | `MARKETPLACE_SYNC_API_URL` (vide = désactivé) |
| Sauvegarde d'une fiche | Marquage pour l'export CSV Salesforce (repris par l'envoi groupé, cf. planifiés) | `salesforce.csv_actif` |
| Adresse modifiée | Vérification BAN (France) ou Geoapify (étranger) : compléments sûrs appliqués, écarts en suggestion à arbitrer | `GEOAPIFY_API_KEY` pour l'étranger |
| Textes modifiés | Analyse des doublons de textes entre fiches (empreinte SimHash) | `pim.seuil_distance_simhash`, `pim.longueur_min_texte_doublon` |
| Publication / republication | Planification des traductions en 6 langues (seuls les textes modifiés sont retraduits) | `GOOGLE_TRANSLATE_API_KEY` |
| Traduction terminée | Redescente de la fiche traduite vers la marketplace | — |
| Archivage | Retrait de la marketplace et révocation des documents publiés | — |
| Création de fiche | Attribution automatique des sites de diffusion selon leurs critères géographiques | critères géo des sites |
| Modification d'une liste de valeurs | Synchro du dictionnaire LOV vers la marketplace et traduction des libellés | — |
| Dépôt d'une photo | Génération des variantes web (WebP), empreinte visuelle anti-doublons ; reconnaissance IA (légende, mots-clés) si activée | `dam.seuil_distance_phash` ; `openai.actif` + `openai.reco_auto_active` |
| Retouche acceptée | Régénération des variantes et redescente marketplace | — |
| Dépôt d'un PDF (OCR) | Extraction et suggestions champ par champ ; application automatique au-dessus du seuil de confiance | `box.ocr_active`, `ocr.seuil_application_auto` (0 = tout manuel) |
| Notification Salesforce (webhook) | Rafraîchissement immédiat des fiches notifiées | `SALESFORCE_WEBHOOK_TOKEN` (vide = 404) |
| Invitation, réinitialisation de mot de passe | E-mails avec liens signés à durée limitée | `compte.invitation_validite_heures`, `compte.reset_validite_heures` |
| Toute modification en base | Journal d'audit (qui, quoi, quand) consultable depuis l'historique | `AUDIT_ENABLED` |

### Planifiés (cron)

| Quand | Quoi | Paramètre / gate |
|---|---|---|
| Toutes les minutes | Envoi des e-mails CSV Salesforce en attente (par paquets) | `salesforce.csv_actif` |
| Toutes les 15 minutes | Supervision des traitements en échec, alerte e-mail au-delà du seuil | `alerte.email`, `alerte.seuil_file_echec` |
| Chaque heure | Analyse des anomalies de la médiathèque (orphelins, doublons visuels, variantes manquantes) | — |
| Chaque nuit 3 h | Rafraîchissement des données Salesforce | secrets `SALESFORCE_*` |
| Chaque nuit 4 h | Envoi CSV Salesforce des salles | `salesforce.csv_actif` |
| Chaque nuit 4 h 20 – 4 h 35 | Purges techniques (messages traités, jetons expirés, données de supervision, exports Excel expirés) | `compte.purge_jetons_jours` |
| Le 1ᵉʳ du mois | Mise à jour des référentiels d'accès (aéroports, grandes villes) et des classements Atout France — imports sans gate — puis vérification des statuts d'établissements | `sirene.verif_statut_actif` |
| Lundi 8 h / 14 h | Préparation puis envoi des relances de complétude | `completude.rappel_auto_actif`, `completude.seuil_rappel`, `completude.delai_rappel_jours` |
