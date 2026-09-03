<?php

declare(strict_types=1);

namespace App\Ocr\Service;

final class OcrExtractionConsolidator
{
    /**
     * @param list<array{first_page: int, response: array<string, mixed>}> $batches
     *
     * @return array{suggestions: list<array<string, mixed>>, raw: array<string, mixed>, agent: string|null, model: string|null}
     */
    public function consolidate(array $batches): array
    {
        $best = [];
        $alternatives = [];
        $agent = $model = null;
        foreach ($batches as $batch) {
            $response = $batch['response'];
            $agent ??= is_string($response['ai_agent_info']['processor'] ?? null) ? $response['ai_agent_info']['processor'] : null;
            if (null === $model && is_array($response['ai_agent_info']['models'] ?? null)) {
                $names = [];
                foreach ($response['ai_agent_info']['models'] as $item) {
                    if (is_array($item) && is_string($item['name'] ?? null)) {
                        $names[] = $item['name'];
                    }
                }
                $model = [] === $names ? null : implode(', ', array_unique($names));
            }
            foreach ($this->fields($response) as $field) {
                $path = $field['path'];
                if ($this->empty($field['value'])) {
                    continue;
                }
                $pages = array_values(array_map(static fn (int $page): int => $page + $batch['first_page'] - 1, $field['pages']));
                $candidate = ['path' => $path, 'value' => $field['value'], 'confidence' => $field['confidence'], 'pages' => $pages, 'first_page' => $batch['first_page']];
                $alternatives[$path][] = $candidate;
                if (!isset($best[$path]) || $this->better($candidate, $best[$path])) {
                    $best[$path] = $candidate;
                }
            }
        }
        ksort($best);
        $suggestions = array_values(array_map(static function (array $item): array {
            unset($item['first_page']);

            return $item;
        }, $best));

        return ['suggestions' => $suggestions, 'raw' => ['batches' => $batches, 'alternatives' => $alternatives], 'agent' => $agent, 'model' => $model];
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return list<array<string, mixed>>
     */
    private function fields(array $response): array
    {
        $answer = $response['answer'] ?? $response['fields'] ?? [];
        if (is_string($answer)) {
            try {
                $answer = json_decode($answer, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new BoxProviderException('Réponse structurée Box invalide.', false, null, $e);
            }
        }
        if (!is_array($answer)) {
            throw new BoxProviderException('Réponse structurée Box invalide.');
        }
        $result = [];
        $confidenceMap = is_array($response['confidence_score'] ?? null) ? $response['confidence_score'] : [];
        $referenceMap = is_array($response['reference'] ?? null) ? $response['reference'] : [];
        foreach ($answer as $key => $answerValue) {
            $item = is_array($answerValue) && array_key_exists('value', $answerValue) ? $answerValue : ['value' => $answerValue];
            $path = is_string($item['key'] ?? null) ? $item['key'] : (is_string($key) ? $key : null);
            if (null === $path) {
                continue;
            }
            $confidenceValue = $item['confidence_score'] ?? $item['confidence'] ?? $confidenceMap[$path] ?? null;
            if (is_array($confidenceValue)) {
                $confidenceValue = $confidenceValue['score'] ?? null;
            }
            $confidence = is_numeric($confidenceValue) ? (float) $confidenceValue : null;
            $refs = $item['references'] ?? $item['reference'] ?? $referenceMap[$path] ?? [];
            $pages = $this->pages($refs);
            $result[] = ['path' => $path, 'value' => $item['value'] ?? null, 'confidence' => $confidence, 'pages' => array_values(array_unique($pages))];
        }

        return $result;
    }

    /** @return list<int> */
    private function pages(mixed $references): array
    {
        if (!is_array($references)) {
            return [];
        }
        $pages = [];
        foreach ($references as $key => $value) {
            if (in_array($key, ['page', 'page_number'], true) && is_numeric($value) && (int) $value > 0) {
                $pages[] = (int) $value;
                continue;
            }
            if (is_array($value)) {
                $pages = [...$pages, ...$this->pages($value)];
            }
        }

        return array_values(array_unique($pages));
    }

    private function empty(mixed $value): bool
    {
        return null === $value || '' === $value || [] === $value;
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $current
     */
    private function better(array $candidate, array $current): bool
    {
        $candidateConfidence = $candidate['confidence'] ?? -1.0;
        $currentConfidence = $current['confidence'] ?? -1.0;

        return $candidateConfidence > $currentConfidence || ($candidateConfidence === $currentConfidence && $candidate['first_page'] < $current['first_page']);
    }
}
