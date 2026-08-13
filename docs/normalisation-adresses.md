# Normalisation et vérification des adresses

_Mis en place le 2026-08-13. Deux niveaux : un référentiel statique appliqué
partout, et une vérification contre l'API Adresse (BAN) à la demande._

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

## Hors périmètre (décisions du 2026-08-13)

- **Étranger** (~4 900 fiches, sites BPmeetings) : Nominatim/Photon (OSM)
  envisageable plus tard, même architecture de commande.
- **Google Geocoding** : non retenu (coût récurrent + obligation d'afficher
  les résultats sur Google Maps) tant que BAN couvre la France.
- **recherche-entreprises.api.gouv.fr** : sert l'enrichissement légal par
  SIREN (bloc administratif), pas la normalisation postale.
