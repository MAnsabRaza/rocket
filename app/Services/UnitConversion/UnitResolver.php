<?php

namespace App\Services\UnitConversion;

use Illuminate\Support\Facades\Cache;

/**
 * Resolves unit names, factors, precision and labels from configuration.
 *
 * Three M2/M3 requirements are met here rather than in UnitConversionService,
 * so the legacy service and the M1 baseline remain untouched until the
 * approved switch-over:
 *
 *  - Aliases. 'lbs' resolves to 'lb'. The pre-existing UnitConversionServiceTest
 *    calls convertMass(10, 'kg', 'lbs') and receives 10 back, because the
 *    configuration defines 'lb'. Alias resolution removes that class of failure.
 *  - Precision per category, replacing the hardcoded round($x, 2).
 *  - Deterministic cache invalidation. Configuration is cached under a key that
 *    includes a version fingerprint, so an administrative change invalidates it
 *    immediately. The legacy service caches individual results on a 24-hour TTL,
 *    which cannot be invalidated on demand and writes one entry per input value.
 */
final class UnitResolver
{
    private const CACHE_PREFIX = 'unit_config:';

    /** @var array<string, mixed>|null */
    private ?array $resolved = null;

    /** Canonical unit name for a category, or null when unknown. */
    public function resolveUnit(string $category, string $unit): ?string
    {
        $config = $this->config();
        $units  = $config['conversions'][$category] ?? null;

        if ($units === null) {
            return null;
        }

        $unit = trim($unit);

        if (isset($units[$unit])) {
            return $unit;
        }

        $lower = strtolower($unit);
        if (isset($units[$lower])) {
            return $lower;
        }

        return $config['aliases'][$category][$lower] ?? null;
    }

    public function categoryExists(string $category): bool
    {
        return isset($this->config()['conversions'][$category]);
    }

    public function factor(string $category, string $unit): ?float
    {
        $canonical = $this->resolveUnit($category, $unit);

        return $canonical === null
            ? null
            : (float) $this->config()['conversions'][$category][$canonical];
    }

    public function precisionFor(string $category): int
    {
        $precision = $this->config()['precision'] ?? [];

        return (int) ($precision[$category] ?? $precision['default'] ?? 2);
    }

    public function roundingMode(): string
    {
        return (string) ($this->config()['rounding_mode'] ?? 'half_up');
    }

    /** Category-scoped label, resolving the 'lb' collision between mass and force. */
    public function label(string $category, string $unit, bool $short = false): string
    {
        $config    = $this->config();
        $canonical = $this->resolveUnit($category, $unit) ?? $unit;

        if (isset($config['category_labels'][$category][$canonical])) {
            return $config['category_labels'][$category][$canonical];
        }

        $key = $short ? 'short_labels' : 'display_labels';

        return $config[$key][$canonical] ?? $canonical;
    }

    /** @return array<int, string> Canonical unit names, excluding any marked inactive. */
    public function activeUnits(string $category): array
    {
        $config   = $this->config();
        $units    = array_keys($config['conversions'][$category] ?? []);
        $inactive = $config['inactive_units'][$category] ?? [];

        return array_values(array_diff($units, $inactive));
    }

    public function isActive(string $category, string $unit): bool
    {
        $canonical = $this->resolveUnit($category, $unit);

        return $canonical !== null
            && ! in_array($canonical, $this->config()['inactive_units'][$category] ?? [], true);
    }

    /**
     * Whether configuration permits converting between two units.
     *
     * An empty or absent category means unrestricted, which is the default and
     * preserves every conversion in the M2 baseline. Listing pairs switches the
     * category to allow-list mode. Identity is always permitted.
     */
    public function pathAllowed(string $category, string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        $paths = $this->config()['allowed_paths'][$category] ?? [];

        if (empty($paths)) {
            return true;
        }

        foreach ($paths as $pair) {
            if (($pair[0] ?? null) === $from && ($pair[1] ?? null) === $to) {
                return true;
            }
        }

        return false;
    }

    /** True when the category restricts conversion routes. */
    public function pathsRestricted(string $category): bool
    {
        return ! empty($this->config()['allowed_paths'][$category] ?? []);
    }

    /**
     * Extensible per-unit metadata, returned verbatim.
     *
     * The conversion engine never interprets these keys, so a consumer can
     * attach what it needs without a schema change. Omit the unit to get the
     * whole category.
     */
    public function metadata(string $category, ?string $unit = null): array
    {
        $forCategory = $this->config()['metadata'][$category] ?? [];

        if ($unit === null) {
            return $forCategory;
        }

        $canonical = $this->resolveUnit($category, $unit) ?? $unit;

        return $forCategory[$canonical] ?? [];
    }

    /** Applies the configured precision and rounding mode. */
    public function round(float $value, string $category): float
    {
        $precision = $this->precisionFor($category);

        return match ($this->roundingMode()) {
            'half_even' => round($value, $precision, PHP_ROUND_HALF_EVEN),
            'half_down' => round($value, $precision, PHP_ROUND_HALF_DOWN),
            default     => round($value, $precision, PHP_ROUND_HALF_UP),
        };
    }

    /**
     * Cached configuration.
     *
     * The cache key embeds a fingerprint of the configuration, so any change
     * produces a different key and the previous entry is bypassed immediately.
     * This is the deterministic invalidation M3 requires; no TTL is involved.
     */
    public function config(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $raw = [
            'conversions'     => config('units.conversions', []),
            'aliases'         => config('units.aliases', []),
            'precision'       => config('units.precision', []),
            'rounding_mode'   => config('units.rounding_mode', 'half_up'),
            'display_labels'  => config('units.display_labels', []),
            'short_labels'    => config('units.short_labels', []),
            'category_labels' => config('units.category_labels', []),
            'inactive_units'  => config('units.inactive_units', []),
            'base_units'      => config('units.base_units', []),
            'allowed_paths'   => config('unit_rules.allowed_paths', []),
            'metadata'        => config('unit_rules.metadata', []),
        ];

        $key = self::CACHE_PREFIX . substr(md5(serialize($raw)), 0, 16);

        return $this->resolved = Cache::rememberForever($key, fn () => $raw);
    }

    /** Discards the in-process copy. Cached entries are superseded by fingerprint. */
    public function flush(): void
    {
        $this->resolved = null;
    }
}
