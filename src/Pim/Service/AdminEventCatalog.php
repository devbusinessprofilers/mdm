<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Account\Message\InternalUserInvited;
use App\Account\MessageHandler\InternalUserInvitedHandler;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\RegenerateMedia;
use App\Dam\MessageHandler\DeleteMediaHandler;
use App\Dam\MessageHandler\MediaUploadedHandler;
use App\Dam\MessageHandler\RegenerateMediaHandler;
use App\Pim\Message\IndexFiche;
use App\Pim\MessageHandler\IndexFicheHandler;
use App\Pim\MessageHandler\MediaProcessedHandler;
use App\Shared\Message\MediaProcessed;
use App\Shared\Message\MediaUploaded;

final class AdminEventCatalog
{
    /**
     * @return list<array{
     *     type: class-string,
     *     label: string,
     *     domain: string,
     *     trigger: string,
     *     transport: string,
     *     worker: string,
     *     handler: class-string,
     *     result: string,
     *     next: string|null
     * }>
     */
    public function events(): array
    {
        return [
            [
                'type' => MediaUploaded::class,
                'label' => 'Image déposée',
                'domain' => 'DAM',
                'trigger' => 'Ajout ou remplacement d’une image sur un lieu.',
                'transport' => 'dam',
                'worker' => 'worker-dam',
                'handler' => MediaUploadedHandler::class,
                'result' => 'Lit l’original privé, génère les six variantes WebP publiques et marque le média comme traité.',
                'next' => 'Émet « Image traitée ».',
            ],
            [
                'type' => MediaProcessed::class,
                'label' => 'Image traitée',
                'domain' => 'PIM',
                'trigger' => 'Fin réussie de la génération des variantes DAM.',
                'transport' => 'pim',
                'worker' => 'worker-pim',
                'handler' => MediaProcessedHandler::class,
                'result' => 'Signale la modification de la fiche du lieu afin que le PIM expose le nouvel état du média.',
                'next' => null,
            ],
            [
                'type' => RegenerateMedia::class,
                'label' => 'Régénération demandée',
                'domain' => 'DAM',
                'trigger' => 'Action « Régénérer » ou modification du cadrage d’une image.',
                'transport' => 'dam',
                'worker' => 'worker-dam',
                'handler' => RegenerateMediaHandler::class,
                'result' => 'Recrée et remplace les six variantes publiques à partir de l’original privé.',
                'next' => null,
            ],
            [
                'type' => DeleteMedia::class,
                'label' => 'Suppression d’image demandée',
                'domain' => 'DAM',
                'trigger' => 'Suppression ou remplacement d’une image, ou suppression du lieu associé.',
                'transport' => 'dam',
                'worker' => 'worker-dam',
                'handler' => DeleteMediaHandler::class,
                'result' => 'Supprime l’original privé et les variantes publiques, puis marque le média comme supprimé.',
                'next' => null,
            ],
            [
                'type' => IndexFiche::class,
                'label' => 'Réindexation d’une fiche',
                'domain' => 'PIM',
                'trigger' => 'Création ou modification d’un lieu, changement de ses images ou suppression d’un média.',
                'transport' => 'pim',
                'worker' => 'worker-pim',
                'handler' => IndexFicheHandler::class,
                'result' => 'Reconstruit le document de recherche de la fiche utilisé par les listes et recherches PIM.',
                'next' => null,
            ],
            [
                'type' => InternalUserInvited::class,
                'label' => 'Invitation utilisateur interne',
                'domain' => 'Compte',
                'trigger' => 'Un Super Admin invite un éditeur ou un validateur.',
                'transport' => 'mail',
                'worker' => 'worker-mail',
                'handler' => InternalUserInvitedHandler::class,
                'result' => 'Envoie un lien signé, valable 24 heures et utilisable une seule fois, pour créer le mot de passe.',
                'next' => null,
            ],
        ];
    }

    public function labelFor(string $type): string
    {
        foreach ($this->events() as $event) {
            if ($event['type'] === $type) {
                return $event['label'];
            }
        }

        $position = strrpos($type, '\\');

        return false === $position ? $type : substr($type, $position + 1);
    }
}
