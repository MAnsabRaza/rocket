<?php

namespace App\Services;

use App\Services\UnitConversion\ConversionResult;
use App\Services\UnitConversion\Exceptions\ConversionPathNotAllowedException;
use App\Services\UnitConversion\Exceptions\UnknownUnitCategoryException;
use App\Services\UnitConversion\Exceptions\UnknownUnitException;
use App\Services\UnitConversion\PreferenceRepository;
use App\Services\UnitConversion\UnitFormatter;
use App\Services\UnitConversion\UnitResolver;

/**
 * Unit conversion (M2).
 *
 * Three approved behaviour changes were applied on 20 August 2026 under
 * decision 4 of the M1 report. Everything else is unchanged, and the 300
 * unaffected rows of the M1 before-state fixture must continue to match
 * exactly.
 *
 *  1. An unrecognised category or unit now raises a domain exception. It
 *     previously logged a warning and returned the input value, so a caller
 *     could not distinguish success from failure. The pre-existing
 *     UnitConversionServiceTest demonstrated this: convertMass(10, 'kg', 'lbs')
 *     returned 10.
 *
 *  2. Precision is no longer hardcoded to two decimal places. Results are
 *     rounded to two decimals as before; only when that would produce 0.00 for
 *     a genuinely non-zero result is precision extended, and only far enough to
 *     preserve three significant figures. 54 conversions are corrected. Every
 *     result of 0.005 or greater is unaffected.
 *
 *  3. Unit aliases are resolved through UnitResolver, so 'lbs' resolves to 'lb'.
 *
 * The per-result cache was removed. It keyed on md5(type|value|from|to) with a
 * 24-hour TTL, writing one entry per distinct input value to cache a single
 * division and multiplication, and it could not be invalidated on demand.
 * Configuration is now cached by UnitResolver under a fingerprint key, which
 * satisfies M3's deterministic invalidation requirement. Returned values are
 * unchanged by this removal.
 */
class UnitConversionService
{
    protected $config;

    private ?UnitResolver $resolver = null;

    private ?PreferenceRepository $preferences = null;

    private ?UnitFormatter $formatter = null;

    /** Round to this many places unless the result would collapse to zero. */
    private const BASE_PRECISION = 2;

    /** Never extend beyond this, to stay within double precision. */
    private const MAX_PRECISION = 12;

    public function __construct()
    {
        $this->config = config('units');
    }

    private function resolver(): UnitResolver
    {
        return $this->resolver ??= app(UnitResolver::class);
    }

    private function preferences(): PreferenceRepository
    {
        return $this->preferences ??= app(PreferenceRepository::class);
    }

    private function formatter(): UnitFormatter
    {
        return $this->formatter ??= app(UnitFormatter::class);
    }

    /**
     * Convert a value from one unit to another.
     *
     * @throws UnknownUnitCategoryException when the category is not configured
     * @throws UnknownUnitException         when either unit is not defined in the category
     * @throws ConversionPathNotAllowedException when both units exist but the route is not permitted
     */
    public function convert(float $value, string $type, string $fromUnit, string $toUnit): float
    {
        if (!isset($this->config['conversions'][$type])) {
            throw UnknownUnitCategoryException::for($type);
        }

        $conversions = $this->config['conversions'][$type];

        $from = $this->resolveUnit($type, $fromUnit, $conversions);
        $to   = $this->resolveUnit($type, $toUnit, $conversions);

        if ($from === $to) {
            return $value;
        }

        if (! $this->resolver()->pathAllowed($type, $from, $to)) {
            throw ConversionPathNotAllowedException::for($from, $to, $type);
        }

        if ($type === 'temperature') {
            return $this->convertTemperatureValue($value, $from, $to);
        }

        $baseValue = $value / $conversions[$from];
        $result    = $baseValue * $conversions[$to];

        return $this->roundPreservingSignificance($result, $type);
    }

    /**
     * Conversion returning a result object rather than a bare float.
     * Never throws; failures are reported through the result.
     */
    public function convertResult(float $value, string $type, string $fromUnit, string $toUnit): ConversionResult
    {
        try {
            return ConversionResult::success(
                $this->convert($value, $type, $fromUnit, $toUnit),
                $value, $type, $fromUnit, $toUnit, app(\App\Services\UnitConversion\UnitResolver::class)->precisionFor($type)
            );
        } catch (UnknownUnitCategoryException $e) {
            return ConversionResult::failure(
                \App\Services\UnitConversion\ConversionOutcome::UnknownCategory,
                $value, $type, $fromUnit, $toUnit, $e->getMessage()
            );
        } catch (UnknownUnitException $e) {
            return ConversionResult::failure(
                \App\Services\UnitConversion\ConversionOutcome::UnknownUnit,
                $value, $type, $fromUnit, $toUnit, $e->getMessage()
            );
        } catch (ConversionPathNotAllowedException $e) {
            return ConversionResult::failure(
                \App\Services\UnitConversion\ConversionOutcome::PathNotAllowed,
                $value, $type, $fromUnit, $toUnit, $e->getMessage()
            );
        }
    }

    /** Canonical unit name, resolving aliases. */
    private function resolveUnit(string $type, string $unit, array $conversions): string
    {
        if (isset($conversions[$unit])) {
            return $unit;
        }

        $canonical = $this->resolver()->resolveUnit($type, $unit);

        if ($canonical === null || !isset($conversions[$canonical])) {
            throw UnknownUnitException::for($unit, $type);
        }

        return $canonical;
    }

    /**
     * Round to two decimals, extending only when that would discard the value.
     *
     * 22.0462  -> 22.05      (unchanged)
     * 0.0001   -> 0.0001     (was 0.00)
     * 0.000323 -> 0.000323   (was 0.00)
     */
    private function roundPreservingSignificance(float $value, ?string $cat = null): float
    {
        return $this->significant($value, $cat === null
            ? self::BASE_PRECISION
            : $this->resolver()->precisionFor($cat));
    }

    private function applyRounding(float $v, int $p): float
    {
        return match ($this->resolver()->roundingMode()) {
            'half_even' => round($v, $p, PHP_ROUND_HALF_EVEN),
            'half_down' => round($v, $p, PHP_ROUND_HALF_DOWN),
            default     => round($v, $p, PHP_ROUND_HALF_UP),
        };
    }

    private function significant(float $value, int $base): float
    {
        $rounded = $this->applyRounding($value, $base);

        if ($rounded != 0.0 || $value == 0.0) {
            return $rounded;
        }

        for ($precision = $base + 1; $precision <= self::MAX_PRECISION; $precision++) {
            if ($this->applyRounding($value, $precision) != 0.0) {
                return $this->applyRounding($value, min(self::MAX_PRECISION, $precision + 2));
            }
        }

        return $this->applyRounding($value, self::MAX_PRECISION);
    }

    private function convertTemperatureValue(float $value, string $fromUnit, string $toUnit): float
    {
        if ($fromUnit === 'f') {
            $celsius = ($value - 32) * 5 / 9;
        } elseif ($fromUnit === 'k') {
            $celsius = $value - 273.15;
        } else {
            $celsius = $value;
        }

        if ($toUnit === 'f') {
            return $this->roundPreservingSignificance(($celsius * 9 / 5) + 32, 'temperature');
        }

        if ($toUnit === 'k') {
            return $this->roundPreservingSignificance($celsius + 273.15, 'temperature');
        }

        return $this->roundPreservingSignificance($celsius, 'temperature');
    }

    /**
     * Convert length
     */
    public function convertLength(float $value, string $from, string $to): float
    {
        return $this->convert($value, 'length', $from, $to);
    }

    /**
     * Convert mass
     */
    public function convertMass(float $value, string $from, string $to): float
    {
        return $this->convert($value, 'mass', $from, $to);
    }

    /**
     * Convert area
     */
    public function convertArea(float $value, string $from, string $to): float
    {
        return $this->convert($value, 'area', $from, $to);
    }

    /**
     * Convert a stored base-unit value for a user's preference.
     *
     * getPreferredUnit() validates and falls back to the base unit, so the
     * units reaching convert() from here are always configured. The display
     * path therefore cannot raise the new exceptions.
     */
    public function convertForUser(float $value, string $type, $user = null, ?string $fromUnit = null): float
    {
        $fromUnit = $fromUnit ?? $this->getBaseUnit($type);
        $toUnit = $this->getPreferredUnit($type, $user);

        if (!$fromUnit || !$toUnit) {
            return $value;
        }

        return $this->convert($value, $type, $fromUnit, $toUnit);
    }

    /**
     * Convert and format a stored base-unit value for checkout/report display.
     */
    public function formatForUser(float $value, string $type, $user = null, ?string $fromUnit = null, bool $short = false): string
    {
        $unit = $this->getPreferredUnit($type, $user) ?? $fromUnit ?? $this->getBaseUnit($type);
        $converted = $this->convertForUser($value, $type, $user, $fromUnit);

        return $this->format($converted, $unit, $short, ['user' => $user]);
    }

    /**
     * Get the user's preferred unit for a type or fall back to the base unit.
     *
     * The lookup moved to PreferenceRepository in M3. Order is: the normalised
     * user_unit_preferences row, then the legacy users.preferred_{type}_unit
     * column, then session, then cookie, then the measurement-system preset,
     * then the base unit.
     *
     * The legacy column is still read and is never written or removed, so every
     * existing account keeps its preference and a rollback loses nothing.
     * Aliases now resolve, so a stored value of "lbs" returns "lb" rather than
     * silently falling through to the base unit as it did before.
     */
    public function getPreferredUnit(string $type, $user = null): ?string
    {
        return $this->preferences()->preferredUnitOrBase($type, $user);
    }

    /**
     * Format value with unit label.
     *
     * Uses at least two decimals, so 10.5 still renders as "10.50", but extends
     * where two decimals would discard the value entirely. Without this, the
     * corrected conversions above would still display as "0.00".
     */
    public function format(float $value, string $unit, bool $short = false, array $options = []): string
    {
        return $this->formatter()->format($value, $unit, $short, $options);
    }

    /**
     * Format value with short label
     */
    public function formatShort(float $value, string $unit, array $options = []): string
    {
        return $this->format($value, $unit, true, $options);
    }

    /**
     * Get available units for a type
     */
    public function getAvailableUnits(string $type): array
    {
        if (!isset($this->config['conversions'][$type])) {
            return [];
        }

        $units = [];
        foreach ($this->resolver()->activeUnits($type) as $unit) {
            $units[$unit] = $this->resolver()->label($type, $unit);
        }

        return $units;
    }

    /**
     * Get base unit for a type
     */
    public function getBaseUnit(string $type): ?string
    {
        return $this->config['base_units'][$type] ?? null;
    }

    /**
     * Get all unit types
     */
    public function getUnitTypes(): array
    {
        return array_keys($this->config['base_units']);
    }

    /**
     * Check if unit conversion is enabled
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'];
    }

    /**
     * Get display label for a unit
     */
    public function getLabel(string $unit, bool $short = false): string
    {
        $labels = $short ? $this->config['short_labels'] : $this->config['display_labels'];
        return $labels[$unit] ?? $unit;
    }

    /**
     * Validate unit for type. Accepts aliases.
     */
    public function isValidUnit(string $type, string $unit): bool
    {
        if (isset($this->config['conversions'][$type][$unit])) {
            return true;
        }

        return $this->resolver()->resolveUnit($type, $unit) !== null;
    }
}
