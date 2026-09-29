<?php

namespace App\Services\Status;

use App\Enums\Status\HeaderPresetCategory;
use App\Models\Status\StatusHeaderPreset;
use App\Models\Status\StatusHeaderTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Header suggestions, templates, and secret redaction.
 * All log/audit/display paths must go through the redact* helpers.
 */
class HeaderPresetManager
{
    /** @var list<string> Always treated as sensitive, even without a preset row. */
    private const ALWAYS_SENSITIVE = [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-auth-token',
        'x-access-token',
    ];

    public const MASK = '••••••••';

    /**
     * @return Collection<string, Collection<int, StatusHeaderPreset>> Presets grouped by category value.
     */
    public function groupedPresets(): Collection
    {
        return StatusHeaderPreset::active()->get()->groupBy(fn (StatusHeaderPreset $preset) => $preset->category->value);
    }

    /**
     * @return Collection<int, StatusHeaderTemplate>
     */
    public function activeTemplates(): Collection
    {
        return StatusHeaderTemplate::active()->get();
    }

    /**
     * Merge a template's headers under the admin's current rows (current wins).
     *
     * @param  array<string, string>  $current
     * @return array<string, string>
     */
    public function applyTemplate(StatusHeaderTemplate $template, array $current = []): array
    {
        return array_merge($template->headers ?? [], $current);
    }

    /**
     * @return list<string> Lower-cased sensitive header names.
     */
    public static function sensitiveNames(): array
    {
        return Cache::remember('status:sensitive-headers', 3600, function (): array {
            $fromPresets = StatusHeaderPreset::where('is_sensitive', true)
                ->pluck('header_name')
                ->map(fn (string $name) => strtolower(trim($name)))
                ->all();

            return array_values(array_unique(array_merge(self::ALWAYS_SENSITIVE, $fromPresets)));
        });
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    public static function redactHeaders(array $headers): array
    {
        $sensitive = self::sensitiveNames();
        $redacted = [];

        foreach ($headers as $name => $value) {
            $redacted[$name] = in_array(strtolower(trim((string) $name)), $sensitive, true) ? self::MASK : $value;
        }

        return $redacted;
    }

    /**
     * @param  array<string, mixed>|null  $auth
     * @return array<string, mixed>|null
     */
    public static function redactAuth(?array $auth): ?array
    {
        if ($auth === null) {
            return null;
        }

        $redacted = $auth;

        foreach (['token', 'password', 'key'] as $secretKey) {
            if (array_key_exists($secretKey, $redacted) && $redacted[$secretKey] !== null && $redacted[$secretKey] !== '') {
                $redacted[$secretKey] = self::MASK;
            }
        }

        if (isset($redacted['headers']) && is_array($redacted['headers'])) {
            $redacted['headers'] = self::redactHeaders($redacted['headers']);
        }

        if (($redacted['category'] ?? null) instanceof HeaderPresetCategory) {
            $redacted['category'] = $redacted['category']->value;
        }

        return $redacted;
    }

    public static function isSensitive(string $headerName): bool
    {
        return in_array(strtolower(trim($headerName)), self::sensitiveNames(), true);
    }
}
