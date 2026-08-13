<?php

declare(strict_types=1);

namespace App\Etl\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client Salesforce en lecture (SOQL) pour le rafraîchissement des données
 * fiche (notes RSE, évaluation client, procédure judiciaire, contrats,
 * statut partenaire). Authentification OAuth 2.0 JWT Bearer : assertion
 * RS256 signée avec la clé privée de la Connected App, échangée contre un
 * jeton d'accès. Le jeton est conservé pour la durée de vie du worker et
 * renouvelé sur 401.
 *
 * Configuration par l'environnement (SALESFORCE_*), vide = désactivé.
 */
final class SalesforceApiClient implements SalesforceClientInterface
{
    private const API_VERSION = 'v63.0';

    private ?string $accessToken = null;
    private ?string $instanceUrl = null;

    private readonly HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $loginUrl,
        private readonly string $clientId,
        private readonly string $username,
        private readonly string $privateKey,
    ) {
        $this->httpClient = $httpClient->withOptions([
            'timeout' => 15,
            'max_duration' => 60,
        ]);
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->loginUrl)
            && '' !== trim($this->clientId)
            && '' !== trim($this->username)
            && '' !== trim($this->privateKey);
    }

    public function query(string $soql): array
    {
        $url = sprintf('/services/data/%s/query?q=%s', self::API_VERSION, urlencode($soql));
        $records = [];
        while (null !== $url) {
            $data = $this->authenticatedGet($url);
            $page = $data['records'] ?? null;
            if (!is_array($page)) {
                throw new SalesforceApiException('Réponse Salesforce invalide : clé "records" absente.');
            }
            foreach ($page as $record) {
                if (is_array($record)) {
                    $records[] = $record;
                }
            }
            $next = $data['nextRecordsUrl'] ?? null;
            $url = is_string($next) && '' !== $next ? $next : null;
        }

        return $records;
    }

    /** @return array<string, mixed> */
    private function authenticatedGet(string $path): array
    {
        if (!$this->isConfigured()) {
            throw new SalesforceApiException('La connexion Salesforce n\'est pas configurée (SALESFORCE_*).');
        }
        $this->authenticate();
        $response = $this->send($path);
        // Jeton expiré entre deux appels du worker : une seule ré-authentification.
        if (401 === $response['status']) {
            $this->accessToken = null;
            $this->authenticate();
            $response = $this->send($path);
        }
        if (200 !== $response['status']) {
            throw new SalesforceApiException(sprintf('Salesforce a répondu HTTP %d à la requête SOQL.', $response['status']));
        }

        return $response['data'];
    }

    /** @return array{status: int, data: array<string, mixed>} */
    private function send(string $path): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                rtrim((string) $this->instanceUrl, '/').$path,
                ['headers' => ['Authorization' => 'Bearer '.$this->accessToken]],
            );
            $status = $response->getStatusCode();

            return [
                'status' => $status,
                'data' => 200 === $status ? $response->toArray(false) : [],
            ];
        } catch (ExceptionInterface $exception) {
            throw new SalesforceApiException('Salesforce est injoignable.', 0, $exception);
        }
    }

    private function authenticate(): void
    {
        if (null !== $this->accessToken) {
            return;
        }
        try {
            $response = $this->httpClient->request(
                'POST',
                rtrim($this->loginUrl, '/').'/services/oauth2/token',
                ['body' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $this->assertion(),
                ]],
            );
            $status = $response->getStatusCode();
            $payload = 200 === $status ? $response->toArray(false) : [];
        } catch (ExceptionInterface $exception) {
            throw new SalesforceApiException('Salesforce est injoignable pour l\'authentification.', 0, $exception);
        }
        $token = $payload['access_token'] ?? null;
        $instanceUrl = $payload['instance_url'] ?? null;
        if (200 !== $status || !is_string($token) || '' === $token || !is_string($instanceUrl) || '' === $instanceUrl) {
            throw new SalesforceApiException(sprintf('Authentification Salesforce refusée (HTTP %d).', $status));
        }
        $this->accessToken = $token;
        $this->instanceUrl = $instanceUrl;
    }

    /** Assertion JWT RS256 signée avec la clé privée de la Connected App. */
    private function assertion(): string
    {
        $header = self::base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = self::base64UrlEncode((string) json_encode([
            'iss' => $this->clientId,
            'aud' => rtrim($this->loginUrl, '/'),
            'sub' => $this->username,
            'exp' => time() + 300,
        ]));
        // Les variables d'environnement mono-ligne portent des « \n » littéraux.
        $key = openssl_pkey_get_private(str_replace('\n', "\n", $this->privateKey));
        if (false === $key) {
            throw new SalesforceApiException('La clé privée Salesforce est illisible (SALESFORCE_PRIVATE_KEY).');
        }
        $signature = '';
        if (!openssl_sign($header.'.'.$claims, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new SalesforceApiException('La signature de l\'assertion JWT Salesforce a échoué.');
        }

        return $header.'.'.$claims.'.'.self::base64UrlEncode($signature);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
