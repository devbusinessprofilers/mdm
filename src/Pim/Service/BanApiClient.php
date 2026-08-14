<?php

declare(strict_types=1);

namespace App\Pim\Service;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client de l'API Adresse (Base Adresse Nationale, data.gouv) — géocodage
 * et vérification d'adresses françaises, gratuit et sans clé. Le lot passe
 * par l'endpoint CSV (/search/csv/) : une requête pour plusieurs milliers
 * de lignes, colonnes retour result_score / result_label / result_name
 * / result_postcode / result_city / latitude / longitude / result_type.
 */
final class BanApiClient implements BanClientInterface
{
    private readonly HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $endpoint,
    ) {
        // Un lot de plusieurs milliers de lignes prend quelques dizaines de
        // secondes côté BAN : bornes larges mais fermes.
        $this->httpClient = $httpClient->withOptions([
            'timeout' => 60,
            'max_duration' => 300,
        ]);
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->endpoint);
    }

    public function verifierLot(array $lignes): array
    {
        if ([] === $lignes) {
            return [];
        }
        $csv = fopen('php://temp', 'r+');
        if (false === $csv) {
            throw new \RuntimeException('Impossible de préparer le CSV du lot BAN.');
        }
        fputcsv($csv, ['id', 'adresse', 'code_postal', 'ville'], escape: '\\');
        foreach ($lignes as $ligne) {
            fputcsv($csv, [$ligne['id'], $ligne['adresse'], $ligne['codePostal'], $ligne['ville']], escape: '\\');
        }
        rewind($csv);
        // columns = colonnes concaténées pour la recherche ; postcode
        // restreint les candidats au code postal fourni.
        $form = new FormDataPart([
            'data' => new DataPart((string) stream_get_contents($csv), 'adresses.csv', 'text/csv'),
            'columns' => 'adresse',
            'postcode' => 'code_postal',
        ]);
        fclose($csv);
        try {
            $response = $this->httpClient->request('POST', rtrim($this->endpoint, '/').'/search/csv/', [
                'headers' => $form->getPreparedHeaders()->toArray(),
                'body' => $form->bodyToIterable(),
            ]);
            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('L\'API Adresse (BAN) est injoignable.', 0, $exception);
        }
        if (200 !== $status) {
            throw new \RuntimeException(sprintf('L\'API Adresse (BAN) a répondu HTTP %d.', $status));
        }

        return $this->parse($body);
    }

    /** @return array<array-key, array{score: ?float, label: ?string, name: ?string, codePostal: ?string, ville: ?string, latitude: ?string, longitude: ?string, type: ?string}> */
    private function parse(string $body): array
    {
        $rows = array_values(array_filter(array_map(
            static fn (string $line): array => str_getcsv($line, escape: '\\'),
            preg_split('/\r\n|\r|\n/', trim($body)) ?: [],
        ), static fn (array $cells): bool => null !== ($cells[0] ?? null)));
        $header = array_shift($rows);
        if (null === $header) {
            return [];
        }
        $index = array_flip(array_map(static fn (?string $column): string => (string) $column, $header));
        $cell = static function (array $row, string $column) use ($index): ?string {
            $value = $row[$index[$column] ?? -1] ?? null;

            return null === $value || '' === trim($value) ? null : trim($value);
        };
        $resultats = [];
        foreach ($rows as $row) {
            $id = $cell($row, 'id');
            if (null === $id) {
                continue;
            }
            $score = $cell($row, 'result_score');
            $resultats[$id] = [
                'score' => null === $score ? null : (float) $score,
                'label' => $cell($row, 'result_label'),
                // La voie seule (sans CP/ville) : ce qui s'écrit dans
                // ruePostale quand l'humain accepte la proposition.
                'name' => $cell($row, 'result_name'),
                'codePostal' => $cell($row, 'result_postcode'),
                'ville' => $cell($row, 'result_city'),
                'latitude' => $cell($row, 'latitude'),
                'longitude' => $cell($row, 'longitude'),
                'type' => $cell($row, 'result_type'),
            ];
        }

        return $resultats;
    }
}
