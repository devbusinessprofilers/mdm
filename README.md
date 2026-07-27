# Business Profilers PIM / DAM

## Premier accès administrateur

Après avoir appliqué les migrations Doctrine, créez le premier compte local depuis un terminal interactif :

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:user:create-super-admin admin@example.com
```

Le mot de passe est saisi deux fois sans être affiché ni transmis dans les arguments du processus. Il doit comporter au moins 12 caractères et ne pas figurer dans les bases de mots de passe compromis. Relancer la commande pour le même super-administrateur actif est sans effet.

Socle Symfony 7.4 LTS du référentiel prestataires MDM.

## Démarrage local

Prérequis : PHP 8.2 ou supérieur, Docker, Composer et MariaDB 11.

## Exploitation

- [Workers et files Symfony Messenger](docs/runbooks/messenger.md)
