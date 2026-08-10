<?php

declare(strict_types=1);

namespace App\Account\MessageHandler;

use App\Account\Message\CollaborateurAccessRequested;
use App\Account\Service\MarketplaceCollaborateurGatewayInterface;
use App\Pim\Entity\FicheCollaborateur;
use App\Pim\Repository\FicheCollaborateurRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Workflow d'invitation : le PIM crée le collaborateur puis le pousse vers la
 * marketplace — c'est elle qui crée le compte utilisateur et envoie l'email
 * d'accès. Salesforce recevra plus tard la même notification, ajoutée ici.
 */
#[AsMessageHandler]
final readonly class CollaborateurAccessRequestedHandler
{
    public function __construct(
        private FicheCollaborateurRepository $collaborateurs,
        private MarketplaceCollaborateurGatewayInterface $marketplace,
    ) {}

    public function __invoke(CollaborateurAccessRequested $message): void
    {
        $collaborateur = $this->collaborateurs->find($message->collaborateurId);
        if (!$collaborateur instanceof FicheCollaborateur || !$collaborateur->isActive()) { return; }
        $this->marketplace->envoyerInvitation($collaborateur, $message->ficheId, trim($message->emailBody));
    }
}
