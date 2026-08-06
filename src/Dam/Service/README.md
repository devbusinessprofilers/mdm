# Services DAM

Services de validation, traitement d'image, pHash et publication propres au DAM.

`PerceptualHashCalculator` normalise l'image avec ImageMagick puis calcule une
DCT 32 × 32. `MediaAnalysisService` conserve l'empreinte, interroge les bandes
indexées et crée une alerte métier sans bloquer la génération des rendus.
`DamDashboardProvider` assemble les vues de supervision ; toutes les requêtes
restent dans les repositories et les actions passent par `DamResourceManager`.
