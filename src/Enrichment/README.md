# Module Enrichment

Le module `Enrichment` livre actuellement la traduction des champs textuels des
fiches et des listes de valeurs (LOV). L'OCR, le tagging, le scraping, Google
Business Profile et les autres fonctions d'IA sont des lots ultérieurs : aucune
de ces fonctions n'est présentée ici comme implémentée.

## Langues et fournisseur

Le français est la langue source. Les langues cibles V1 sont l'anglais,
l'espagnol, l'italien, le néerlandais, le portugais et l'allemand. Le contrat
`TranslationProviderInterface` est implémenté par Google Cloud Translation ; la
clé `GOOGLE_TRANSLATE_API_KEY` reste dans l'environnement ou le gestionnaire de
secrets.

## Déclenchement des traductions de fiches

La traduction automatique concerne une seule fiche à la fois :

- lors de son passage au statut `publiee` ;
- lors de la sauvegarde, par l'administration ou l'API, d'une fiche déjà
  publiée.

`FicheTranslationScheduler` refuse toute fiche non publiée. Il compare
l'empreinte SHA-256 de chaque source et ne planifie que les traductions absentes
ou devenues obsolètes. La sauvegarde d'une fiche inchangée ne rappelle donc pas
Google.

Il n'existe aucun cron, démarrage de worker ou tâche planifiée qui parcourt
automatiquement toutes les fiches. Un traitement en volume n'est possible que
par une demande explicite :

```bash
php bin/console app:translations:schedule --scope=fiches --limit=100
```

La commande accepte `all`, `fiches` ou `lov`, un curseur `--after` pour les
fiches et `--dry-run`. Le bouton « Relancer » d'une fiche ne concerne que cette
fiche.

## Traductions des LOV

Les libellés traduisibles sont persistés pour les définitions et valeurs LOV.
Une création, un changement de libellé ou un clic explicite sur « Relancer
Google » planifie les langues manquantes. L'attribut technique `PRESTATAIRE` et
les attributs non traduisibles sont ignorés.

## Traitement et corrections manuelles

Les demandes passent par l'outbox puis la file Messenger `enrichment`. Les
entités conservent la source, son empreinte, la langue, l'origine, l'état, le
jeton de requête et la dernière erreur. Un résultat ancien est ignoré si son jeton
ne correspond plus à la demande courante.

Une correction manuelle n'est jamais écrasée par Google. Si la source française
change, elle devient obsolète et la nouvelle proposition automatique est
conservée séparément pour validation. Les validateurs BP peuvent consulter,
corriger et relancer les traductions depuis l'administration.

Toute traduction devenue disponible — reçue de Google ou corrigée à la main —
déclenche la resynchronisation de la fiche vers la marketplace via le module
`Etl`.

## Composants principaux

- `Service/FicheTranslationScheduler.php` et
  `Service/FicheTranslationSourceExtractor.php` ;
- `Service/LovTranslationScheduler.php` ;
- `MessageHandler/TranslatePublishedFicheHandler.php` et
  `MessageHandler/TranslateLovLabelHandler.php` ;
- `Controller/FicheTranslationController.php` ;
- `EventSubscriber/TranslationFailureSubscriber.php`.
