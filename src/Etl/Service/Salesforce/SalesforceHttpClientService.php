<?php

namespace App\Etl\Service\Salesforce;


use App\Etl\Service\Salesforce\CustomerPortalPerformanceTraceService;
use Firebase\JWT\JWT;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// SF6

class SalesforceHttpClientService
{
    const VERSION = 'v63.0';
    const MESSAGE_VERSION = 'v5';
    const FILE_VERSION = 'v2';

    /**
     * The SOQL FIELDS function must have a LIMIT of at most 200 on the current
     * API, so we must LIMIT our requests to 200 results when using FIELDS(ALL).
     *
     * @see https://developer.salesforce.com/docs/atlas.en-us.soql_sosl.meta/soql_sosl/sforce_api_calls_soql_select_fields.htm
     */
    public const SOQL_MAX_LIMIT = 200;
    private static array $getCache = [];

    /** @var HttpClientInterface */
    protected $client;

    /** @var LoggerInterface */
    private $loggerInterface;

    /** @var string */
    private $url;

    /** @var array */
    private $headers;

    /** @var CacheItemPoolInterface */
    private $cache;

    /** @var string */
    private $salesforceEnv;

    /** @var string */
    private $salesforceClientId;

    /** @var string */
    private $salesforceUrl;

    /** @var string */
    private $salesforceOrgId;

    /** @var string */
    private $salesforceLoginUrl;

    /** @var string */
    private $adminEmail;

    /** @var string */
    private $racine;

    /** @var string */
    private $instanceUrl;

    public function __construct(
        HttpClientInterface $client,
        LoggerInterface $loggerInterface,
        CacheItemPoolInterface $cache,
        string $salesforceEnv,
        string $salesforceClientId,
        string $salesforceUrl,
        string $salesforceOrgId,
        string $salesforceLoginUrl,
        string $adminEmail,
        string $racine,
        string $instanceUrl,
        private readonly TranslatorInterface $translator,
        private readonly CustomerPortalPerformanceTraceService $customerPortalPerformanceTraceService
    )
    {
        $this->salesforceEnv = $salesforceEnv;
        $this->client = $client;
        $this->loggerInterface = $loggerInterface;
        $this->cache = $cache;
        $this->salesforceClientId = $salesforceClientId;
        $this->salesforceUrl = $salesforceUrl;
        $this->salesforceOrgId = $salesforceOrgId;
        $this->salesforceLoginUrl = $salesforceLoginUrl;
        $this->adminEmail = $adminEmail;
        $this->racine = $racine;
        $this->instanceUrl = $instanceUrl;
    }

    private function setHttpHeaders($addHeaderAccept = false, $headerAccept = null)
    {
        $token = $this->cache->getItem('token');
        if (!$token->get()) {
            $this->getAccessToken();
            $token = $this->cache->getItem('token');
        }
        unset($this->headers['Accept']);
        if ($addHeaderAccept) {
            if($headerAccept) {
                $this->headers['Accept'] = 'application/' . $headerAccept;
            } else {
                $this->headers['Accept'] = '*/*';
            }
        }
        $this->headers["Authorization"] = "Bearer " . $token->get()->access_token;
        $this->headers["Content-Type"] = 'application/json';
    }

    public function getAccessToken()
    {
        // Private key for SF connexion - Compatible with var ENV and file storage
        $vName = 'SALESFORCE_PK';
        if (getenv($vName) === false) {
            $privateKeyFileName = $this->racine . DIRECTORY_SEPARATOR . 'certs/salesforce_' . $this->salesforceEnv . '.key';
            $privateKey = file_get_contents($privateKeyFileName);
        } else {
            $privateKey = getenv($vName);
        }

        $this->loggerInterface->info('===== SF ENV =====', [$this->salesforceEnv], [$this->salesforceEnv]);

        $payload = array(
            "iss" => $this->salesforceClientId,
            "aud" => $this->salesforceLoginUrl,
            "sub" => $this->adminEmail,
            "exp" => (string)(time() + (5 * 60))
        );

        $jwt = JWT::encode($payload, $privateKey, 'RS256', null, ['alg' => 'RS256']);
        $this->loggerInterface->info('===== JWT =====', [$jwt]);

        try {
            $oauthUrl = $this->salesforceLoginUrl . '/services/oauth2/token';
            $startedAt = microtime(true);
            $response = $this->client->request('POST', $oauthUrl, [
                'body' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => "$jwt"
                ]
            ]);
            $content = $response->getContent();
            $data = json_decode($content);
            $this->recordSalesforceTiming('POST', $oauthUrl, $startedAt, [
                'type' => 'oauth',
                'statusCode' => 200,
                'responseBytes' => strlen($content),
            ]);

            $item = $this->cache->getItem('token');
            if (!$item->isHit()) {
                $item->set($data);
                $item->expiresAfter(86400);
                $this->cache->save($item);
            }

            $this->loggerInterface->info('===== OAUTH INFORMATION =====', [$data]);
        } catch (ClientExceptionInterface $exception) {
            $this->recordSalesforceTiming('POST', $this->salesforceLoginUrl . '/services/oauth2/token', $startedAt ?? microtime(true), [
                'type' => 'oauth',
                'statusCode' => $exception->getResponse()->getStatusCode(),
                'error' => true,
            ]);
            $this->loggerInterface->error('Salesforce returned the following error: ' . $exception->getResponse()->getContent(false));
            throw new \Exception($this->translator->trans('salesforce.connection_error', [], 'main'));
        } catch (\Exception $exception) {
            $this->recordSalesforceTiming('POST', $this->salesforceLoginUrl . '/services/oauth2/token', $startedAt ?? microtime(true), [
                'type' => 'oauth',
                'error' => true,
            ]);
            $this->loggerInterface->info('===== Encountred exception while trying to connect to salesforce =====', [$exception]);
            throw new \Exception($this->translator->trans('salesforce.connection_error', [], 'main'));
        } catch (InvalidArgumentException $e) {
            throw new \Exception('Invalid Argument Exception');
        }
    }

    /**
     * @return string
     */
    public function getSalesforceUrl(): string
    {
        return $this->salesforceUrl;
    }

    /**
     * @return string
     */
    public function getSalesforceOrgId(): string
    {
        return $this->salesforceOrgId;
    }

    /**
     * @param string $url
     */
    private function init(string $url, $addHeaderAccept = false, $headerAccept = null)
    {
        $this->url = $this->instanceUrl . $url;
        $this->setHttpHeaders($addHeaderAccept, $headerAccept);
    }

    private function handleResult(string $method, array $body = [])
    {
        try {
            if ($method == 'GET') {
                $response = $this->client->request('GET', $this->url, [
                    'headers' => $this->headers,
                    'body' => []
                ]);
            } else {
                $response = $this->client->request($method, $this->url, [
                    'headers' => $this->headers,
                    'body' => json_encode($body, JSON_UNESCAPED_UNICODE)
                ]);
            }

            return $response->getContent(); //$response->toArray(); toArray() return json_decoded data

        } catch (HttpExceptionInterface $exception) {
            $response = $exception->getResponse();
            $content = $response ? $response->getContent(false) : null; // `false` to avoid another exception
            $statusCode = $response ? $response->getStatusCode() : $exception->getCode();

            $this->loggerInterface->error('===== ERROR WHEN CALLING SALESFORCE =====', [
                'method' => $method,
                'url' => $this->url,
                'headers' => $this->headers,
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'statusCode' => $statusCode,
                'exceptionMessage' => $exception->getMessage(),
                'salesforceResponse' => $content,
            ]);

            if ($statusCode === Response::HTTP_UNAUTHORIZED) {
                $this->cache->delete('token');
                $this->loggerInterface->info('Due to a potential invalid token, we removed it from the cache.');
            }

            return new JsonResponse($content ?: $exception->getMessage(), $statusCode ?: 500);
        } catch (\Exception $exception) {
            $this->loggerInterface->error('===== ERROR WHEN CALLING SALESFORCE WITH =====', [
                'method' => $method,
                'url' => $this->url,
                'headers' => $this->headers,
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'errorCode' => $exception->getCode(),
                'errorMessage' => $exception->getMessage()
            ]);

            return new JsonResponse($exception->getMessage(), $exception->getCode() ? $exception->getCode() : 500);
        }
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function prepareGetRequest(string $url, ?string $headerAccept = null): array
    {
        if ($headerAccept) {
            $this->init($url, true, $headerAccept);
        } else {
            $this->init($url);
        }

        return [$this->url, $this->headers];
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function preparePostRequest(string $url): array
    {
        $this->init($url, true);

        return [$this->url, $this->headers];
    }

    private function resolveResponseContent(ResponseInterface $response, string $method, string $url, array $headers, array $body = []): string|JsonResponse
    {
        try {
            return $response->getContent();
        } catch (HttpExceptionInterface $exception) {
            $errorResponse = $exception->getResponse();
            $content = $errorResponse ? $errorResponse->getContent(false) : null;
            $statusCode = $errorResponse ? $errorResponse->getStatusCode() : $exception->getCode();

            $this->loggerInterface->error('===== ERROR WHEN CALLING SALESFORCE =====', [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'statusCode' => $statusCode,
                'exceptionMessage' => $exception->getMessage(),
                'salesforceResponse' => $content,
            ]);

            if ($statusCode === Response::HTTP_UNAUTHORIZED) {
                $this->cache->delete('token');
                $this->loggerInterface->info('Due to a potential invalid token, we removed it from the cache.');
            }

            return new JsonResponse($content ?: $exception->getMessage(), $statusCode ?: 500);
        } catch (\Exception $exception) {
            $this->loggerInterface->error('===== ERROR WHEN CALLING SALESFORCE WITH =====', [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'errorCode' => $exception->getCode(),
                'errorMessage' => $exception->getMessage()
            ]);

            return new JsonResponse($exception->getMessage(), $exception->getCode() ? $exception->getCode() : 500);
        }
    }

    /**
     * @param string $url
     * @param array $body
     */
    public function post(string $url, array $body)
    {
        $this->init($url, true);

        $this->loggerInterface->info('########### - SF POST : ' . $url . ' - ' . serialize($body));

        return $this->handleTimedResult('POST', $url, $body);
    }

    /**
     * @param string $url
     * @throws TransportExceptionInterface
     */
    public function get(string $url, $headerAccept = null)
    {
        if (!isset(self::$getCache[$url])) {
            [$requestUrl, $headers] = $this->prepareGetRequest($url, $headerAccept);
            $startedAt = microtime(true);

            try {
                $response = $this->client->request('GET', $requestUrl, [
                    'headers' => $headers,
                    'body' => []
                ]);
                self::$getCache[$url] = $this->resolveResponseContent($response, 'GET', $requestUrl, $headers);
                $this->recordSalesforceTiming('GET', $url, $startedAt, [
                    'cacheHit' => false,
                    'statusCode' => $this->resolveStatusCode(self::$getCache[$url]),
                    'error' => $this->isErrorResult(self::$getCache[$url]),
                    'responseBytes' => $this->resolveResponseBytes(self::$getCache[$url]),
                ]);
            } catch (\Throwable $exception) {
                $this->recordSalesforceTiming('GET', $url, $startedAt, [
                    'cacheHit' => false,
                    'error' => true,
                ]);

                throw $exception;
            }

            $this->loggerInterface->info('########### - SF GET WITHOUT CACHE : ' . $url . ' - ' . $url);
        } else {
            $this->customerPortalPerformanceTraceService->recordSalesforceCall('GET', $url, 0, [
                'cacheHit' => true,
                'statusCode' => $this->resolveStatusCode(self::$getCache[$url]),
                'error' => $this->isErrorResult(self::$getCache[$url]),
                'responseBytes' => $this->resolveResponseBytes(self::$getCache[$url]),
            ]);
            $this->loggerInterface->info('########### - SF GET WITH CACHE : ' . $url . ' - ' . $url);
        }
        return self::$getCache[$url];
    }

    /**
     * @param array<int, array{url: string, headerAccept?: ?string}> $requests
     * @return array<int, string|JsonResponse>
     * @throws TransportExceptionInterface
     */
    public function getConcurrent(array $requests): array
    {
        $results = [];
        $pendingResponses = [];
        $responseMetadata = [];

        foreach ($requests as $index => $request) {
            $url = (string) ($request['url'] ?? '');
            $headerAccept = $request['headerAccept'] ?? null;

            if ($url === '') {
                $results[$index] = new JsonResponse('Empty Salesforce URL.', Response::HTTP_INTERNAL_SERVER_ERROR);
                continue;
            }

            if (isset(self::$getCache[$url])) {
                $results[$index] = self::$getCache[$url];
                $this->customerPortalPerformanceTraceService->recordSalesforceCall('GET', $url, 0, [
                    'cacheHit' => true,
                    'statusCode' => $this->resolveStatusCode(self::$getCache[$url]),
                    'error' => $this->isErrorResult(self::$getCache[$url]),
                    'responseBytes' => $this->resolveResponseBytes(self::$getCache[$url]),
                ]);
                $this->loggerInterface->info('########### - SF GET WITH CACHE : ' . $url . ' - ' . $url);
                continue;
            }

            [$requestUrl, $headers] = $this->prepareGetRequest($url, $headerAccept);
            $startedAt = microtime(true);

            try {
                $response = $this->client->request('GET', $requestUrl, [
                    'headers' => $headers,
                    'body' => []
                ]);
            } catch (\Throwable $exception) {
                $this->recordSalesforceTiming('GET', $url, $startedAt, [
                    'cacheHit' => false,
                    'error' => true,
                ]);

                throw $exception;
            }

            $pendingResponses[] = $response;
            $responseMetadata[spl_object_id($response)] = [
                'index' => $index,
                'cacheKey' => $url,
                'url' => $requestUrl,
                'headers' => $headers,
                'startedAt' => $startedAt,
            ];

            $this->loggerInterface->info('########### - SF GET WITHOUT CACHE : ' . $url . ' - ' . $url);
        }

        if ($pendingResponses === []) {
            ksort($results);

            return array_values($results);
        }

        foreach ($this->client->stream($pendingResponses) as $response => $chunk) {
            if (!$chunk->isLast()) {
                continue;
            }

            $metadata = $responseMetadata[spl_object_id($response)] ?? null;
            if (!is_array($metadata)) {
                continue;
            }

            $result = $this->resolveResponseContent(
                $response,
                'GET',
                $metadata['url'],
                $metadata['headers']
            );

            self::$getCache[$metadata['cacheKey']] = $result;
            $results[$metadata['index']] = $result;
            $this->recordSalesforceTiming('GET', $metadata['cacheKey'], $metadata['startedAt'], [
                'cacheHit' => false,
                'statusCode' => $this->resolveStatusCode($result),
                'error' => $this->isErrorResult($result),
                'responseBytes' => $this->resolveResponseBytes($result),
            ]);
        }

        ksort($results);

        return array_values($results);
    }

    /**
     * Executes several POST requests concurrently. Unlike GET, POST responses are
     * never memoized in the static cache.
     *
     * @param array<int, array{url?: string, body?: array<string, mixed>}> $requests
     * @return array<int, string|JsonResponse>
     * @throws TransportExceptionInterface
     */
    public function postConcurrent(array $requests): array
    {
        $results = [];
        $pendingResponses = [];
        $responseMetadata = [];

        foreach ($requests as $index => $request) {
            $url = (string) ($request['url'] ?? '');
            $body = is_array($request['body'] ?? null) ? $request['body'] : [];

            if ($url === '') {
                $results[$index] = new JsonResponse('Empty Salesforce URL.', Response::HTTP_INTERNAL_SERVER_ERROR);
                continue;
            }

            [$requestUrl, $headers] = $this->preparePostRequest($url);
            $startedAt = microtime(true);

            try {
                $response = $this->client->request('POST', $requestUrl, [
                    'headers' => $headers,
                    'body' => json_encode($body, JSON_UNESCAPED_UNICODE)
                ]);
            } catch (\Throwable $exception) {
                $this->recordSalesforceTiming('POST', $url, $startedAt, [
                    'error' => true,
                ]);

                throw $exception;
            }

            $pendingResponses[] = $response;
            $responseMetadata[spl_object_id($response)] = [
                'index' => $index,
                'shortUrl' => $url,
                'url' => $requestUrl,
                'headers' => $headers,
                'body' => $body,
                'startedAt' => $startedAt,
            ];

            $this->loggerInterface->info('########### - SF POST CONCURRENT : ' . $url . ' - ' . serialize($body));
        }

        if ($pendingResponses === []) {
            ksort($results);

            return array_values($results);
        }

        foreach ($this->client->stream($pendingResponses) as $response => $chunk) {
            if (!$chunk->isLast()) {
                continue;
            }

            $metadata = $responseMetadata[spl_object_id($response)] ?? null;
            if (!is_array($metadata)) {
                continue;
            }

            $result = $this->resolveResponseContent(
                $response,
                'POST',
                $metadata['url'],
                $metadata['headers'],
                $metadata['body']
            );

            $results[$metadata['index']] = $result;
            $this->recordSalesforceTiming('POST', $metadata['shortUrl'], $metadata['startedAt'], [
                'statusCode' => $this->resolveStatusCode($result),
                'error' => $this->isErrorResult($result),
                'responseBytes' => $this->resolveResponseBytes($result),
            ]);
        }

        ksort($results);

        return array_values($results);
    }

    /**
     * @param string $url
     */
    public function getFile(string $url)
    {
        $this->url = $this->instanceUrl . $url;
        $token = $this->cache->getItem('token');

        $this->headers["Authorization"] = "Bearer " . $token->get()->access_token;
        $this->headers["Content-Type"] = 'application/json';
        $this->headers["Accept"] = '*/*';

        return $this->handleTimedResult('GET', $url, [], 'file');
    }

    /**
     * @param string $url
     */
    public function delete(string $url)
    {
        $this->init($url);

        $this->loggerInterface->info('########### - SF DELETE : ' . $url);

        return $this->handleTimedResult('DELETE', $url);
    }

    /**
     * @param string $url
     * @param array $body
     */
    public function patch (string $url, array $body)
    {
        $this->init($url);

        $this->loggerInterface->info('########### - SF PATCH : ' . $url . ' - ' . serialize($body));

        return $this->handleTimedResult('PATCH', $url, $body);
    }

    private function handleTimedResult(string $method, string $url, array $body = [], ?string $type = null)
    {
        $startedAt = microtime(true);
        $result = $this->handleResult($method, $body);

        $this->recordSalesforceTiming($method, $url, $startedAt, [
            'type' => $type,
            'statusCode' => $this->resolveStatusCode($result),
            'error' => $this->isErrorResult($result),
            'responseBytes' => $this->resolveResponseBytes($result),
        ]);

        return $result;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function recordSalesforceTiming(string $method, string $url, float $startedAt, array $context = []): void
    {
        $this->customerPortalPerformanceTraceService->recordSalesforceCall(
            $method,
            $url,
            (microtime(true) - $startedAt) * 1000,
            $context
        );
    }

    private function resolveStatusCode(mixed $result): ?int
    {
        return $result instanceof JsonResponse ? $result->getStatusCode() : 200;
    }

    private function isErrorResult(mixed $result): bool
    {
        return $result instanceof JsonResponse && $result->getStatusCode() >= 400;
    }

    private function resolveResponseBytes(mixed $result): ?int
    {
        return is_string($result) ? strlen($result) : null;
    }
}
