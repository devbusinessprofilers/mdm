# Enrichissement des fiches — comparatif Google Places vs API gratuites

Analyse de faisabilité pour couvrir les besoins du cahier des charges (§10.1, §10.2)
en matière d'enrichissement automatique des fiches (horaires, attributs, statut
d'activité, affiliation groupe/chaîne, visibilité géographique).

Parc réel au 2026-08-24 : 26 591 fiches — Lieu 19 835, Restaurant 2 918,
Activité 2 304, Service 1 534. Sous-ensemble pertinent GMB (Lieu + Restaurant)
= 22 753, dont ~85 % France, adresses présentes à ~99 %.

## Comparatif par donnée

| Donnée | Cible CDC | Google Places (New) | Prix Google | API gratuite | Prix gratuit | Verdict |
|---|---|---|---|---|---|---|
| **Statut ouvert / fermé** | §4 qualité (fiches mortes) | `businessStatus` (tier Pro) | ~49 $/1000* | **Sirene / Recherche-entreprises** (`état administratif`) | **0 €** | 🟢 Gratuit ≥ Google (officiel FR) |
| **Horaires d'ouverture** | §10.2 GMB | `regularOpeningHours` (Enterprise) | ~65 $/1000* | **Geoapify Places / OSM**, DATAtourisme | **0 €** | 🟡 Google + complet, gratuit suffisant en ville |
| **Classification / catégorie** | §10.2 GMB | `types` | inclus | **Sirene (code NAF)** + OSM | **0 €** | 🟢 Gratuit suffisant |
| **Attributs services** (végétarien, réservable, terrasse, alcool…) | §10.2 GMB (services dispo.) | attributs (Enterprise+Atmosphere) | ~65 $/1000* | **Geoapify / OSM**, DATAtourisme | **0 €** | 🟡 Google + riche, OSM correct pour restos urbains |
| **Accessibilité PMR** | Champ fiche | `accessibilityOptions` | inclus Enterprise | **Geoapify / OSM** (`wheelchair`) | **0 €** | 🟢 Gratuit OK |
| **Type de cuisine** | Fiche Restaurant | via attributs | Atmosphere | **OSM** (`cuisine`), DATAtourisme | **0 €** | 🟢 Gratuit OK |
| **Téléphone / site web** | Contact | `phone`, `websiteUri` | Pro/Enterprise | **Sirene**, DATAtourisme, OSM | **0 €** | 🟢 Déjà couvert (recherche-entreprises) |
| **Descriptions** | §10.2 génération textes | `editorialSummary` | Enterprise | **DATAtourisme** (Licence Ouverte) + IA (OpenAI existant) | **0 €** | 🟢 DATAtourisme + IA |
| **Photos** | §A.2 / DAM §5 | refs photos | droits ❌ | **DATAtourisme** (réutilisables) | **0 €** | 🟢 Seul DATAtourisme est réutilisable |
| **Note / avis** | — | `rating`, `reviews` | Atmosphere + droits ❌ | — (déjà via Salesforce) | — | ⚪ Inutile ici |
| **Affiliation groupe / chaîne** | §10.2 | ✗ (Google ne le donne pas) | — | **Wikidata** + unité légale Sirene | **0 €** | 🟢 Gratuit est la seule voie |
| **Coordonnées GPS** | Localisation | `location` | Essentials 5 $/1000 | **BAN / Geoapify** (déjà en place) | **0 €** | 🟢 Déjà fait (99 % du parc) |
| **Infos légales** (SIREN/SIRET/TVA) | Fiche | ✗ | — | **Sirene** (déjà intégré) | **0 €** | 🟢 Déjà fait |
| **Visibilité géo auto** (site thématique) | §10.1 | ✗ (calcul interne) | — | **Calcul haversine local** (GPS + centres sites) | **0 €** | 🟢 100 % interne |

<sub>* Google : lookup Text Search (~32 $/1000) + Place Details selon tier. « Blended
enrichissement » ≈ 65 $/1000. Geoapify : 3 000 req/jour gratuites = 90 000/mois,
cache/stockage illimité autorisé. Sirene, DATAtourisme, Wikidata, BAN : gratuits sans
plafond utile.</sub>

**Lecture :** sur les 14 données, Google n'est strictement supérieur que sur la densité
des horaires/attributs. Tout le reste est couvert gratuitement, et 3 données (chaîne,
légal, visibilité géo) ne sont accessibles QUE par les voies gratuites. Budget cible :
~0 € au lieu de ~1 000 €, Google réservé au bouche-trou restaurants (~20-50 €).

## Budget Google (rappel, si voie payante retenue en bouche-trou)

Modèle : Text Search (~32 $/1000) + Place Details horaires/attributs (~25-35 $/1000)
≈ 65 $/1000. `businessStatus` seul (tier Pro) ≈ 49 $/1000.

| Scénario | Fiches | Coût Google | ≈ € |
|---|---|---|---|
| Restaurants publiés (ROI max) | 1 781 | ~116 $ | ~107 € |
| Tous les restaurants | 2 918 | ~190 $ | ~175 € |
| Restos complets + `businessStatus` sur tous les Lieux | 22 753 | ~1 160 $ | ~1 070 € |
| Enrichissement complet Lieu + Restaurant | 22 753 | ~1 480 $ | ~1 360 € |
| Tout le parc (Activités/Services inclus, déconseillé) | 26 591 | ~1 730 $ | ~1 590 € |

## Sources gratuites — références

- **Sirene / Recherche-entreprises** (`recherche-entreprises.api.gouv.fr`) : déjà intégrée
  (pré-remplissage légal à la création). Fournit `etat_administratif` (Actif/Cessé),
  code NAF, unité légale. Gratuit, sans clé.
- **Geoapify Places / Place Details** : clé déjà présente (géocodage étranger). Basé sur
  OpenStreetMap, 400+ catégories, horaires/attributs/PMR. Free 3 000 req/jour,
  cache/stockage illimité. https://www.geoapify.com/places-api/
- **DATAtourisme** : open data officiel FR (DGE + Tourisme & Territoires). Hébergements,
  restaurants, activités : descriptions, horaires, équipements, photos. Licence Ouverte
  2.0 (réutilisation commerciale autorisée). Clé sur formulaire, gratuit.
  https://www.datatourisme.fr/utiliser-les-donnees/
- **Wikidata** (SPARQL, gratuit) : affiliation groupe/chaîne/marque des enseignes notables.
- **Overture Maps / Foursquare Open Places** : datasets POI mondiaux téléchargeables
  gratuitement (catégorie, site web, attributs) pour du matching en masse.
- **BAN** : déjà intégrée (adresse + GPS).

## Stratégie retenue : gratuit d'abord, Google en bouche-trou

Toutes les pistes sortent en **suggestion à arbitrer** (colonne Source, Accepter/Ignorer
un clic — mécanisme « Suggestions en attente » déjà branché pour la BAN), jamais
d'écriture directe. Chaque piste = une valeur `Source` + un service + une commande batch
cron + un paramètre de gate (défaut off).

| Ordre | Piste | Source | Effort | Coût |
|---|---|---|---|---|
| 1 | Statut fermé | Sirene (`etat_administratif`) | ~1,5 j | 0 € |
| 2 | Attributs + horaires restaurants | Geoapify Places (OSM) | ~3 j | 0 € |
| 3 | Descriptions / équipements / photos hôtels-activités | DATAtourisme | ~4-5 j | 0 € |
| 4 | Affiliation groupe/chaîne | Wikidata + unité légale Sirene | ~4 j | 0 € |
| 5 | Bouche-trou horaires/attributs restos non couverts | Google Places | — | ~20-50 € |

Point 1 en priorité : plus grosse valeur (qualité référentiel) pour l'effort le plus
faible, sans risque juridique. Les 4 pistes réutilisent le même châssis.
