<?php

declare(strict_types=1);

namespace App\Ocr\Service;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class BoxExtractProvider implements DocumentExtractionProviderInterface
{
    private ?string $accessToken = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $subjectType,
        private readonly string $subjectId,
        private readonly string $folderId,
        private readonly string $apiUrl,
        private readonly string $uploadUrl,
        private readonly string $agent,
    ) {}

    public function upload(string $path, string $filename): string
    {
        if (!is_file($path)) { throw new \InvalidArgumentException('Le lot PDF temporaire est introuvable.'); }
        $form = new FormDataPart([
            'attributes' => json_encode(['name' => $filename, 'parent' => ['id' => $this->folderId]], JSON_THROW_ON_ERROR),
            'file' => DataPart::fromPath($path, $filename, 'application/pdf'),
        ]);
        $response = $this->authorizedRequest('POST', rtrim($this->uploadUrl, '/').'/2.0/files/content', ['headers' => $form->getPreparedHeaders()->toArray(), 'body' => $form->bodyToIterable()]);
        $payload = $this->json($response, 'upload');
        $id = $payload['entries'][0]['id'] ?? null;
        if (!is_string($id) || '' === $id) { throw new BoxProviderException('Réponse Box upload invalide.', false); }
        return $id;
    }

    /** @return array<string, mixed> */
    public function extract(string $fileId, array $fields): array
    {
        $payload = [
            'items' => [['id' => $fileId, 'type' => 'file']],
            'fields' => $fields,
            'include_confidence_score' => true,
            'include_reference' => true,
            'ai_agent' => ['type' => 'ai_agent_id', 'id' => $this->agent],
        ];
        return $this->json($this->authorizedRequest('POST', rtrim($this->apiUrl, '/').'/2.0/ai/extract_structured', ['json' => $payload]), 'extraction');
    }

    public function delete(string $fileId): void
    {
        $response = $this->authorizedRequest('DELETE', rtrim($this->apiUrl, '/').'/2.0/files/'.rawurlencode($fileId));
        $status = $response->getStatusCode();
        if (!in_array($status, [204, 404], true)) { $this->throwForStatus($response, 'suppression'); }
    }

    /** @param array<string, mixed> $options */
    private function authorizedRequest(string $method, string $url, array $options = []): ResponseInterface
    {
        $options['auth_bearer'] = $this->token();
        $response = $this->httpClient->request($method, $url, $options);
        if (401 !== $response->getStatusCode()) { return $response; }
        $this->accessToken = null;
        $options['auth_bearer'] = $this->token();
        return $this->httpClient->request($method, $url, $options);
    }

    private function token(): string
    {
        if (null !== $this->accessToken) { return $this->accessToken; }
        $response = $this->httpClient->request('POST', rtrim($this->apiUrl, '/').'/oauth2/token', ['body' => [
            'grant_type' => 'client_credentials', 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
            'box_subject_type' => $this->subjectType, 'box_subject_id' => $this->subjectId,
        ]]);
        $payload = $this->json($response, 'authentification');
        $token = $payload['access_token'] ?? null;
        if (!is_string($token) || '' === $token) { throw new BoxProviderException('Réponse d’authentification Box invalide.', false); }
        return $this->accessToken = $token;
    }

    /** @return array<string, mixed> */
    private function json(ResponseInterface $response, string $operation): array
    {
        if ($response->getStatusCode() >= 300) { $this->throwForStatus($response, $operation); }
        try { $data = $response->toArray(false); } catch (\Throwable $e) { throw new BoxProviderException('Réponse JSON Box invalide pour '.$operation.'.', false, null, $e); }
        return $data;
    }

    private function throwForStatus(ResponseInterface $response, string $operation): never
    {
        $status = $response->getStatusCode();
        $retryAfter = null;
        if (429 === $status) {
            $headers = $response->getHeaders(false);
            $retryAfter = isset($headers['retry-after'][0]) ? max(1, (int) $headers['retry-after'][0]) : 60;
        }
        throw new BoxProviderException(sprintf('Box %s a échoué (HTTP %d).', $operation, $status), 429 === $status || $status >= 500, $retryAfter);
    }
}
