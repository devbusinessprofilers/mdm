# Entités PIM

Les classes placées dans ce dossier sont communes à plusieurs familles du PIM :
`Fiche`, `Localisation`, les listes de valeurs et le document de recherche.

Les entités propres à une famille doivent être rangées dans un sous-dossier :

- `Lieu/` contient tout le modèle métier du Lieu ;
- les futures familles suivront la même convention (`Restaurant/`,
  `Activite/`, etc.) ;
- une classe ne revient à la racine que si au moins plusieurs familles peuvent
  réellement la réutiliser.

Le fonctionnement détaillé de `Fiche`, du CRUD et des fixtures est expliqué
dans le [README principal](../../../README.md).
