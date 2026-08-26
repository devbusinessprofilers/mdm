<?php

declare(strict_types=1);

namespace App\Pim\Service;

final class AdminWorkflowCatalog
{
    /**
     * @return list<array{
     *     title: string,
     *     domain: string,
     *     summary: string,
     *     steps: list<string>,
     *     rules: list<string>
     * }>
     */
    public function workflows(): array
    {
        return [
            [
                'title' => 'Validation et publication des fiches Lieu, Activité, Restaurant et Service',
                'domain' => 'PIM',
                'summary' => 'Le workflow éditorial reste piloté par les collaborateurs du PIM.',
                'steps' => [
                    'En cours → soumission par un éditeur après validation complète de la fiche Lieu, Activité, Restaurant ou Service.',
                    'En attente de validation → validation ou refus motivé par un validateur.',
                    'Validée → publication par un validateur ; les traductions sont alors planifiées.',
                    'Publiée → archivage par un validateur ; les documents publics sont alors révoqués.',
                ],
                'rules' => [
                    'Une sauvegarde applique les contraintes Draft ; la soumission ajoute les contraintes Submission.',
                    'Une modification par un validateur conserve le statut et toutes les métadonnées du workflow ; une modification par un éditeur renvoie la fiche en cours.',
                    'L’historique de la fiche est réservé aux validateurs et administrateurs et ne permet aucune restauration.',
                ],
            ],
            [
                'title' => 'Écriture depuis le site externe',
                'domain' => 'API',
                'summary' => 'L’API v1 permet de lire et modifier un Lieu, une Activité, un Restaurant ou un Service existant sans piloter son workflow.',
                'steps' => [
                    'Lire la fiche via /api/v1/lieux, /api/v1/activites, /api/v1/restaurants ou /api/v1/services et conserver son ETag et sa version.',
                    'Renvoyer la version dans If-Match pour chaque PATCH, POST, PUT ou DELETE.',
                    'Traiter la réponse : 428 sans If-Match, 409 si la version est devenue obsolète.',
                ],
                'rules' => [
                    'L’API externe ne crée, ne soumet, ne valide, ne publie et n’archive aucune fiche.',
                    'Toute mutation conserve le statut et les métadonnées du workflow courant.',
                    'Le prestataire peut renseigner source, mots-clés et échéance des droits ; seul un validateur interne peut valider ou révoquer ces droits.',
                    'Le contrat interactif complet est disponible dans la Documentation API Platform.',
                ],
            ],
            [
                'title' => 'Publication d’un document DAM',
                'domain' => 'DAM',
                'summary' => 'L’original reste privé ; seule une copie confirmée par le worker devient publiquement accessible.',
                'steps' => [
                    'Dépôt privé et contrôle du fichier, puis validation des métadonnées et des droits.',
                    'Calcul pHash et signalement non bloquant des doublons exacts ou visuellement proches dans la supervision DAM.',
                    'Demande de publication → outbox → file dam → copie vers le stockage public.',
                    'Confirmation du worker → URL CDN exposée ; révocation, remplacement, archivage ou suppression retirent la copie.',
                ],
                'rules' => [
                    'La fiche doit être publiée et les droits d’utilisation validés.',
                    'Les échéances sont signalées à J-30 puis comme expirées, sans dépublication automatique de la fiche.',
                    'Les documents confidentiels restent toujours privés et ne disposent jamais d’URL publique.',
                    'Scopes JWT : documents:read, documents:write, documents:private et documents:publish.',
                ],
            ],
            [
                'title' => 'Audit et historique',
                'domain' => 'Audit',
                'summary' => 'Chaque transaction métier produit une révision append-only regroupant uniquement les champs réellement modifiés.',
                'steps' => [
                    'Ouvrir l’historique depuis une fiche Lieu, Activité, Restaurant ou Service ; l’accès est réservé aux validateurs et administrateurs.',
                    'Filtrer la chronologie par action, champ, acteur ou période, puis ouvrir le détail ancienne/nouvelle valeur.',
                    'Identifier l’origine pim, external_api, workflow ou worker grâce au contexte et à l’identifiant de corrélation.',
                ],
                'rules' => [
                    'L’historique commence à l’activation du lot, sans création de fausses révisions antérieures.',
                    'Aucune modification, suppression ou restauration d’une révision n’est proposée.',
                ],
            ],
            [
                'title' => 'Déploiement et exploitation',
                'domain' => 'Ops',
                'summary' => 'La migration, l’application web et les workers DAM doivent connaître le même contrat.',
                'steps' => [
                    'Exécuter app:lieux:validate, app:activites:validate, app:restaurants:validate et app:services:validate avant la migration et corriger explicitement toute donnée incompatible.',
                    'Appliquer doctrine:migrations:migrate, puis redémarrer worker-dam, worker-pim et worker-outbox.',
                    'Planifier les pHash historiques avec app:dam:analyze-media, puis traiter les alertes dans /admin/dam.',
                    'Contrôler doctrine:schema:validate et la supervision de l’outbox et des files ci-dessus.',
                    'En dev uniquement, app:dev:database:clean vide la base et les deux stockages DAM ; doctrine:fixtures:load --group=pim-demo --append recharge Lieux, Activités, Restaurants et Services.',
                ],
                'rules' => [
                    'Aucune valeur historique inconnue n’est tronquée ou corrigée automatiquement.',
                    'Les originaux documentaires ne passent jamais dans le générateur de variantes d’images.',
                ],
            ],
        ];
    }
}
