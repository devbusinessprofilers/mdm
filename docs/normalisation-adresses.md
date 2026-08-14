# Normalisation et vérification des adresses

_Mis en place le 2026-08-13. Trois niveaux : un référentiel statique appliqué
partout, une vérification contre l'API Adresse (BAN) pour la France, et
depuis le 2026-08-14 une vérification Geoapify pour l'étranger._

## Référentiel géographique français (statique, sans API)

`ReferentielGeographiqueFrancais` embarque les libellés canoniques INSEE des
18 régions et 101 départements, avec une clé de repli (casse, accents,
tirets, apostrophes) : « Île-De-France », « ile de france » →
« Île-de-France ». Une valeur hors référentiel (région étrangère, Monaco)
n'est jamais dégradée.

L'entité `Localisation` normalise **à l'écriture** (`changeRegion`,
`changeDepartement`) et répare le zéro initial des codes postaux français
(« 6130 » → « 06130 », gardé par le pays : les CP belges ou suisses à
4 chiffres sont légitimes). Tous les chemins d'écriture sont couverts :
formulaires, API portail, imports legacy, futur front.

### Reprise du stock

```bash
docker compose exec -e APP_DEBUG=0 php php -d memory_limit=1G bin/console app:localisation:normaliser [--dry-run]
```

Rattrape l'existant, signale les codes postaux incohérents avec leur
département (vraies erreurs source, à arbitrer à la main), replanifie la
sync marketplace des fiches modifiées, sans transition de workflow.
Exécuté sur `mdm_reel` le 2026-08-13 : 12 283 régions et 449 départements
recasés, 10 incohérences CP ↔ département signalées. À rejouer en prod
après l'import initial.

## Vérification BAN (`app:localisation:verifier`)

API Adresse de data.gouv (`BAN_API_ENDPOINT`, gratuite, sans clé) — France
uniquement, par lots CSV de 1 000 (plafond aligné sur
`FicheRepository::findWithLocalisationAfter`).

```bash
# 1. Rapport seul (aucune écriture) : var/tmp/verification-adresses-*.csv
docker compose exec -e APP_DEBUG=0 php php -d memory_limit=1G bin/console app:localisation:verifier

# 2. Application des enrichissements sûrs au-dessus du seuil (défaut 0.85)
docker compose exec -e APP_DEBUG=0 php php -d memory_limit=1G bin/console app:localisation:verifier --appliquer [--seuil=0.85] [--code=N]
```

Quatre paniers : **conformes** (score ≥ seuil, CP et ville concordants),
**enrichissables** (conformes avec GPS à remplir), **corrections
proposées** (score ≥ seuil mais CP ou ville différents — jamais écrites,
à arbitrer via le rapport), **douteuses** (score < seuil : abréviations
« Bd »/« Av. », CP cedex inconnus de la BAN, lieux-dits — ou vraie adresse
fausse).

`--appliquer` n'écrit que le non-destructif : GPS manquants, CP/ville
vides, recasage d'une ville identique à la casse/accents près. Chaque
passage trace `ban_score` + `ban_verifie_le` sur `pim_localisation`
(future base d'un filtre « adresses douteuses »). Fiches modifiées →
sync marketplace, sans transition de workflow.

Exécuté sur `mdm_reel` le 2026-08-13 : 21 711 vérifiées — 7 936 conformes,
**4 769 GPS remplis**, 302 corrections proposées, 8 704 douteuses. Le
stock français sans GPS est passé de ~8 300 à 3 549.

## Vérification continue (au fil de l'eau)

Depuis le 2026-08-14, chaque création ou modification d'adresse française
déclenche automatiquement une vérification BAN, sans commande à lancer :

- **Déclenchement** : `IndexFicheHandler` (point de convergence de toutes
  les mutations) compare `address_fingerprint` à `ban_fingerprint` — l'empreinte
  de l'adresse au moment de la dernière vérification. Si elles diffèrent (ou
  jamais vérifiée), il enfile `VerifierAdresseFiche` via l'outbox.
- **Handler** (`VerifierAdresseFicheHandler`) : lot BAN d'une ligne, mêmes
  règles que la commande batch (logique partagée dans
  `LocalisationBanVerifier`) : enrichissements sûrs appliqués (GPS manquants,
  CP/ville vides, recasage accents), trace posée, re-index si modifié. Aucune
  transition de workflow. L'empreinte est capturée **après** enrichissement,
  donc pas de boucle vérification → index → vérification.
- **Trace** sur `pim_localisation` : `ban_score`, `ban_verifie_le`,
  `ban_fingerprint`, et en cas d'écart `ban_proposition` (JSON : label BAN,
  voie seule `name`, niveau `type`, CP, ville, GPS) + `ban_ecart` (booléen
  indexé). Ces colonnes sont exclues de l'audit JSON
  (`DoctrineAuditSubscriber::IGNORED_FIELDS` — le binaire de l'empreinte
  n'est pas sérialisable).
- **Arbitrage humain en un clic** (`AdresseSuggestionArbitre`), depuis deux
  écrans : le bloc **« Suggestions en attente »** en bas de l'onglet
  Informations générales de la fiche (une ligne source « BAN » avec la
  proposition et le score ; le bloc accueillera d'autres sources — IA — plus
  tard), et `/qualite` → onglet « Conflits à arbitrer » → tableau
  « Suggestions d'adresse » (mêmes boutons, retour sur place). Réservé aux
  validateurs (`ROLE_BP_VALIDATOR`), audité en source `ban`.
  - **Accepter** applique la proposition : la rue seulement quand le
    résultat BAN est au niveau rue/numéro (`housenumber`/`street` — jamais
    quand la BAN n'a trouvé qu'une commune), puis CP, ville et GPS. Refusé
    si l'adresse a changé depuis la vérification (empreintes différentes).
  - **Ignorer** garde la saisie et solde l'écart ; la trace du passage
    reste, pas de nouvelle vérification tant que l'adresse ne change pas.
  - Rien n'est écrit automatiquement sur une adresse divergente : sans
    clic, l'écart attend.

Le stock existant se (re)peuple au fil des modifications ; pour alimenter
l'écran Qualité immédiatement, rejouer `app:localisation:verifier
--appliquer` (renseigne `ban_proposition`/`ban_ecart` sur tout le stock).

## Étranger : Geoapify (depuis le 2026-08-14)

Les ~4 800 adresses hors de France (code ISO systématiquement renseigné :
DE, IT, ES, BE…) sont vérifiées par **Geoapify** (géocodage mondial sur
données OpenStreetMap) avec exactement les mêmes règles — paniers,
enrichissements sûrs, trace `ban_*`, écarts arbitrables en un clic. Le
routage est porté par `GeocodeurAdresses` : la BAN pour la France, Geoapify
sinon (source affichée dans le bloc et /qualite).

- **Config** : `GEOAPIFY_API_KEY` (`.env.local`, vide = étranger désactivé,
  la France continue), `GEOAPIFY_API_ENDPOINT` (défaut api.geoapify.com).
  Voir `docs/SECRETS.md`.
- **Client** (`GeoapifyClient`) : une adresse → endpoint simple ; un lot →
  job batch asynchrone (1 000 max, ~0,5 crédit/adresse), **un job par pays**
  (filtre `countrycode`, garde anti-homonymes : un résultat hors du pays
  demandé vaut « aucun résultat fiable »). `result_type` aligné sur les
  niveaux BAN (`building`/`amenity` → `housenumber`).
- **Crédits** : plan gratuit 3 000/jour — la reprise du stock étranger
  (~4 800 adresses en batch ≈ 2 400 crédits) tient en un jour :
  `app:localisation:verifier --appliquer --fournisseur=geoapify`.
  `--fournisseur=ban|geoapify|tous` borne la dépense ; le rapport CSV porte
  une colonne fournisseur.
- **Attribution** (obligation du plan gratuit) : « © Geoapify — données
  © OpenStreetMap contributors » affichée sous le bloc et le tableau
  Qualité.

## Écartés

- **Google Geocoding** : non retenu (coût récurrent + obligation d'afficher
  les résultats sur Google Maps).
- **Nominatim/Photon auto-hébergés** : possibles plus tard derrière
  `GeocodeurEtrangerInterface` sans toucher la chaîne, si le volume ou la
  dépendance externe le justifie.
- **recherche-entreprises.api.gouv.fr** : sert l'enrichissement légal par
  SIREN (bloc administratif), pas la normalisation postale.
