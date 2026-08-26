<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Account\Message\InternalUserInvited;
use App\Account\Message\InternalUserPasswordResetRequested;
use App\Account\MessageHandler\InternalUserInvitedHandler;
use App\Account\MessageHandler\InternalUserPasswordResetRequestedHandler;
use App\Dam\Message\DeleteMedia;
use App\Dam\Message\AnalyzeMedia;
use App\Dam\Message\PublishDocument;
use App\Dam\Message\RegenerateMedia;
use App\Dam\Message\UnpublishDocument;
use App\Dam\MessageHandler\DeleteMediaHandler;
use App\Dam\MessageHandler\AnalyzeMediaHandler;
use App\Dam\MessageHandler\MediaUploadedHandler;
use App\Dam\MessageHandler\PublishDocumentHandler;
use App\Dam\MessageHandler\RegenerateMediaHandler;
use App\Dam\MessageHandler\UnpublishDocumentHandler;
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
                'trigger' => 'Ajout ou remplacement d’une photo sur une fiche Lieu, Activité, Restaurant ou Service.',
                'transport' => 'dam',
                'worker' => 'worker-dam',
                'handler' => MediaUploadedHandler::class,
                'result' => 'Lit l’original privé, génère les sept variantes WebP publiques et marque le média comme traité.',
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
                'result' => 'Signale la modification de la fiche afin que le PIM expose le nouvel état du média et sa version.',
                'next' => null,
            ],
            [
                'type' => AnalyzeMedia::class,
                'label' => 'Analyse pHash demandée',
                'domain' => 'DAM',
                'trigger' => 'Rattrapage des images historiques sans empreinte perceptuelle via la commande DAM dédiée.',
                'transport' => 'dam',
                'worker' => 'worker-dam',
                'handler' => AnalyzeMediaHandler::class,
                'result' => 'Calcule le pHash, recherche les doublons exacts ou visuellement proches et crée une alerte non bloquante.',
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
                'result' => 'Recrée et remplace les sept variantes publiques à partir de l’original privé.',
                'next' => null,
            ],
            [
                'type' => DeleteMedia::class,
                'label' => 'Suppression d’image demandée',
                'domain' => 'DAM',
                'trigger' => 'Suppression ou remplacement d’une image, ou suppression de la fiche Lieu, Activité, Restaurant ou Service associée.',
                'transport' => 'dam',
                'worker' => 'worker-dam',
                'handler' => DeleteMediaHandler::class,
                'result' => 'Supprime l’original privé et les variantes publiques, puis marque le média comme supprimé.',
                'next' => null,
            ],
            [
                'type' => PublishDocument::class,
                'label' => 'Publication de document demandée',
                'domain' => 'DAM',
                'trigger' => 'Publication explicite d’un document publiable dont les droits sont validés sur une fiche publiée.',
                'transport' => 'dam',
                'worker' => 'worker-dam',
                'handler' => PublishDocumentHandler::class,
                'result' => 'Copie l’original privé vers le stockage public et confirme l’URL CDN uniquement après réussite.',
                'next' => null,
            ],
            [
                'type' => UnpublishDocument::class,
                'label' => 'Révocation de document demandée',
                'domain' => 'DAM',
                'trigger' => 'Dépublication, remplacement, archivage ou suppression d’un document publié.',
                'transport' => 'dam',
                'worker' => 'worker-dam',
                'handler' => UnpublishDocumentHandler::class,
                'result' => 'Supprime idempotemment la copie publique puis confirme le retour à un état privé.',
                'next' => null,
            ],
            [
                'type' => IndexFiche::class,
                'label' => 'Réindexation d’une fiche',
                'domain' => 'PIM',
                'trigger' => 'Création ou modification d’une fiche Lieu, Activité, Restaurant ou Service, changement de ses ressources ou évolution du workflow.',
                'transport' => 'pim',
                'worker' => 'worker-pim',
                'handler' => IndexFicheHandler::class,
                'result' => 'Reconstruit le document de recherche avec uniquement les données actives, utilisé par les listes et recherches PIM.',
                'next' => null,
            ],
            [
                'type' => InternalUserInvited::class,
                'label' => 'Invitation collaborateur interne',
                'domain' => 'Compte',
                'trigger' => 'Un Super Admin invite un éditeur ou un validateur.',
                'transport' => 'mail',
                'worker' => 'worker-mail',
                'handler' => InternalUserInvitedHandler::class,
                'result' => 'Envoie un lien signé à usage unique pour créer le mot de passe, valable pendant la durée paramétrée (compte.invitation_validite_heures).',
                'next' => null,
            ],
            [
                'type' => InternalUserPasswordResetRequested::class,
                'label' => 'Reset de mot de passe interne',
                'domain' => 'Compte',
                'trigger' => 'Un collaborateur demande un reset ou un Super Admin renvoie ses identifiants.',
                'transport' => 'mail',
                'worker' => 'worker-mail',
                'handler' => InternalUserPasswordResetRequestedHandler::class,
                'result' => 'Envoie un lien signé, valable une heure et utilisable une seule fois, sans transmettre de mot de passe.',
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
