# Module Dashboard

Le module `Dashboard` fournit les écrans de pilotage du MDM : tableau de bord
d'accueil, statistiques détaillées, qualité des données, journal des
traitements de fond et vue des traitements en échec. Il ne porte aucune règle
métier : il agrège et présente les données des autres modules.

## Pages

- `/` (`app_mdm_tableau_de_bord`) : accueil avec les files « À traiter »
  (à valider, à publier, suggestions IA, repli, échecs) comptées en direct,
  la santé du référentiel et l'activité par période issues des snapshots ;
- `/admin/tableau-de-bord` (`app_dashboard_index`) : statistiques détaillées
  (volumes, publication, complétude, stockage, croisement pays × type, délai
  de validation, activité par utilisateur, taux de remplissage par champ)
  avec un bouton de recalcul manuel ;
- `/qualite` (`app_mdm_qualite`) : cinq onglets — santé des données par
  gamme, conflits à arbitrer (suggestions OCR, doublons d'adresse), écarts de
  forme, notifications de relance et décisions d'arbitrage ;
- `/outils` (`app_mdm_outils`) : journal unifié des traitements (imports ETL,
  extractions OCR, traductions, médias), chaque ligne renvoyant vers l'écran
  de détail existant ;
- `/admin/traitements-en-echec` (`ROLE_ADMIN`) : échecs de toutes les
  familles, y compris marketplace et outbox, avec messages d'erreur.

## Snapshots et calcul asynchrone

Les statistiques coûteuses sont précalculées dans `dashboard_snapshot`
(payload JSON, type `stats` ou `field_fill`, durée du calcul). Les compteurs
qui doivent être exacts au moment du clic (files, journal, qualité) sont
requêtés en direct par les repositories du module.

- `ComputeDashboardStats` est planifié toutes les 15 minutes par le
  scheduler ;
- `ComputeFieldFillRates` parcourt toutes les fiches par lots (trop coûteux
  pour 15 minutes) et tourne chaque nuit à 4 h 30, redispatché vers la file
  `pim` ;
- les snapshots de plus de 7 jours sont compactés à un par jour, ce qui
  alimente les courbes d'historique sur 30 jours.

Les messages du module sont routés vers le transport `pim`. Un recalcul
immédiat et synchrone des deux snapshots est possible avec :

```bash
php bin/console app:dashboard:recompute
```

## Composants principaux

- `Controller/` : les cinq pages ci-dessus ;
- `Entity/DashboardSnapshot.php` : snapshots persistés ;
- `Service/DashboardStatsCalculator.php` et
  `Service/FieldFillRateCalculator.php` : calculs ;
- `Service/TableauDeBordProvider.php` et
  `Service/DashboardPageProvider.php` : préparation des données pour les
  templates (files avec seuils de sévérité, formatage, sparklines) ;
- `Repository/` : requêtes en direct (files, journal, qualité, activité) et
  accès aux snapshots ;
- `Scheduler/DashboardScheduleProvider.php` : planification récurrente ;
- `Command/RecomputeDashboardCommand.php` : recalcul manuel.

Le module lit les tables de `Pim`, `Dam`, `Etl`, `Ocr`, `Enrichment`, `Audit`
et de l'outbox `Shared`, mais ne publie aucun événement.
