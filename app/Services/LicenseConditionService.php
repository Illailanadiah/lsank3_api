<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class LicenseConditionService
{
    public function resolve(?Model $application, ?Model $technicalReport): array
    {
        return [
            'general' => $this->unique($this->fromSources([$application, $technicalReport], [
                'general_conditions', 'syarat_umum', 'general_terms',
                'license_general_conditions', 'prepared_general_conditions',
            ])),
            'special' => $this->unique($this->fromSources([$application, $technicalReport], [
                'special_conditions', 'syarat_khusus', 'specific_conditions',
                'license_special_conditions', 'prepared_special_conditions',
                'additional_conditions', 'syarat_tambahan',
            ])),
        ];
    }

    private function fromSources(array $sources, array $keys): array
    {
        $result = [];
        foreach (array_filter($sources) as $source) {
            $payload = $source instanceof Model ? $source->toArray() : (array) $source;
            foreach ($keys as $key) {
                if (Arr::has($payload, $key)) {
                    $result = array_merge($result, $this->normalise(Arr::get($payload, $key)));
                }
            }
        }
        return $result;
    }

    private function normalise(mixed $value): array
    {
        if ($value === null || $value === '') return [];
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) return $this->normalise($decoded);
            return collect(preg_split('/\r\n|\r|\n/', trim($value)) ?: [])
                ->map(fn ($line) => preg_replace('/^\s*(?:\d+[.)]|[-*•])\s*/u', '', trim($line)))
                ->filter()->map(fn ($line) => ['text' => $line, 'items' => []])->values()->all();
        }
        if ($value instanceof Collection) $value = $value->all();
        if ($value instanceof Model) $value = $value->toArray();
        if (!is_array($value)) return [['text' => (string) $value, 'items' => []]];
        if (Arr::isAssoc($value) && ($text = $this->text($value)) !== null) {
            return [['text' => $text, 'items' => collect($this->normalise($value['items'] ?? $value['children'] ?? []))->pluck('text')->filter()->values()->all()]];
        }
        $result = [];
        foreach ($value as $item) $result = array_merge($result, $this->normalise($item));
        return $result;
    }

    private function text(array $item): ?string
    {
        foreach (['text', 'condition', 'description', 'value', 'syarat'] as $key) {
            if (filled($item[$key] ?? null)) return trim((string) $item[$key]);
        }
        return null;
    }

    private function unique(array $conditions): array
    {
        return collect($conditions)->filter(fn ($x) => filled($x['text'] ?? null))
            ->unique(fn ($x) => mb_strtolower(trim($x['text'])))->values()->all();
    }
}
