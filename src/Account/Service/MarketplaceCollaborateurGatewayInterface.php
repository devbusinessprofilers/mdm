<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Pim\Entity\FicheCollaborateur;

/**
 * Pousse un collaborateur vers la marketplace : c'est elle qui crée le compte
 * utilisateur et envoie l'email d'accès. Le PIM ne fait que déclarer le
 * collaborateur. Salesforce recevra plus tard la même notification via une
 * seconde implémentation, branchée à côté dans le handler.
 */
interface MarketplaceCollaborateurGatewayInterface
{
    /**
     * @param string $ficheId   ULID de la fiche à laquelle le collaborateur est affilié
     * @param string $emailBody Message personnalisé transmis à la marketplace (optionnel)
     *
     * @throws \RuntimeException quand la marketplace refuse l'envoi (le message sera rejoué)
     */
    public function envoyerInvitation(FicheCollaborateur $collaborateur, string $ficheId, string $emailBody = ''): void;
}
