# Business Profilers PIM / DAM

Socle Symfony 7.4 LTS du référentiel prestataires MDM.

## Démarrage local

Prérequis : PHP 8.2 ou supérieur, Composer et MariaDB 11.

    composer install
    php bin/console doctrine:database:create
    php bin/console doctrine:migrations:migrate --no-interaction
    symfony server:start

Points d'accès :

- application : https://127.0.0.1:8000
- API Platform : https://127.0.0.1:8000/api
- santé : https://127.0.0.1:8000/health

La connexion MariaDB locale peut être adaptée dans un fichier .env.local.

## Contrôles

    composer analyse
    composer test

Les choix d'architecture et les limites des modules sont décrits dans
ARCHITECTURE.md.
