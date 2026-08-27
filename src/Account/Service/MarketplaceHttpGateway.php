<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Pim\Entity\FicheCollaborateur;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Implémentation HTTP de la passerelle marketplace. Tant que l'URL n'est pas
 * configurée (MARKETPLACE_API_URL vide), l'envoi est journalisé et ignoré —
 * le reste du workflow (création PIM, affiliation) n'est jamais bloqué.
 */
#[WithMonologChannel('marketplace_sync')]
final readonly class MarketplaceHttpGateway implements MarketplaceCollaborateurGatewayInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $endpoint,
        private string $token,
    ) {
    }

    public function envoyerInvitation(FicheCollaborateur $collaborateur, string $ficheId, string $emailBody = ''): void
    {
        if ('' === trim($this->endpoint)) {
            $this->logger->warning('Invitation collaborateur non transmise : MARKETPLACE_API_URL n\'est pas configurée.', [
                'collaborateur' => $collaborateur->email(),
                'fiche' => $ficheId,
            ]);

            return;
        }
        try {
            $response = $this->httpClient->request('POST', rtrim($this->endpoint, '/').'/collaborateurs', [
                'headers' => '' === $this->token ? [] : ['Authorization' => 'Bearer '.$this->token],
                'json' => [
                    'email' => $collaborateur->email(),
                    'firstName' => $collaborateur->firstName(),
                    'lastName' => $collaborateur->lastName(),
                    'phone' => $collaborateur->phone(),
                    'language' => $collaborateur->language(),
                    'ficheId' => $ficheId,
                    'message' => $emailBody,
                ],
            ]);
            $status = $response->getStatusCode();
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('La marketplace est injoignable pour l\'invitation du collaborateur.', 0, $exception);
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(sprintf('La marketplace a refusé l\'invitation du collaborateur (HTTP %d).', $status));
        }
        $this->logger->info('Collaborateur transmis à la marketplace pour création de compte.', [
            'collaborateur' => $collaborateur->email(),
            'fiche' => $ficheId,
        ]);
    }
}
