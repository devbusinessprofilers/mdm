# Module DataFixtures

Le module `DataFixtures` fournit des jeux de démonstration pour les quatre
domaines PIM : Lieu, Activité, Restaurant et Service événementiel. Il est
réservé au développement et à la recette ; le bundle
`doctrine/doctrine-fixtures-bundle` est une dépendance `require-dev` et rien
ici ne doit s'exécuter en production.

## Contenu généré

Chaque classe crée par défaut 100 fiches réalistes du type concerné :
libellés variés, LOV existantes, localisation fixe ou mode d'intervention
mobile, tarifs, capacités, salles et accès selon le domaine. Environ 80 % des
fiches sont publiées via `publishForImport()`, le reste demeure `en_cours`,
et chaque fiche reçoit son document d'index de recherche.

Les codes de fiche proviennent du compteur global `pim_fiche_code_counter`
(`PimFixtureTools::nextCode()`), comme en production : ils restent uniques et
séquentiels entre tous les types. L'insertion se fait par lots de 250 avec
`flush()`/`clear()` pour supporter de gros volumes.

## Exécution

```bash
docker compose exec php php bin/console doctrine:fixtures:load \
  --group=pim-demo --append --no-interaction --no-debug
```

Toujours utiliser `--append` : une exécution sans ce flag purge la base.
Chaque type dispose aussi de son propre groupe (`pim-lieux`,
`pim-activites`, `pim-restaurants`, `pim-services`).

Le volume se règle par variable d'environnement, validée par
`PimFixtureTools::count()` (entier positif, plafond 100 000) :

- `PIM_LIEU_FIXTURE_COUNT` ;
- `PIM_ACTIVITE_FIXTURE_COUNT` ;
- `PIM_RESTAURANT_FIXTURE_COUNT` ;
- `PIM_SERVICE_FIXTURE_COUNT`.

Les fixtures sont indépendantes entre elles : aucune
`DependentFixtureInterface`, aucune référence partagée ; seule la séquence de
codes est commune, sérialisée au niveau de la base.

## Composants principaux

- `Pim/LieuFixtures.php`, `Pim/ActiviteFixtures.php`,
  `Pim/RestaurantFixtures.php`, `Pim/ServiceFixtures.php` : générateurs par
  domaine ;
- `Pim/PimFixtureTools.php` : validation du volume et attribution des codes.
