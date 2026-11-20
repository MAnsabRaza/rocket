<?php

namespace App\Services\UnitConversion;

/**
 * Outcome of a batch conversion.
 *
 * Carries a ConversionResult per item, keyed by the caller's original keys,
 * so a partial failure never invalidates the successful items. Required by
 * M4: "per-item status for batch operations".
 */
final class BatchResult
{
    /** @param array<array-key, ConversionResult> $results */
    public function __construct(public readonly array $results) {}

    /** @return array<array-key, ConversionResult> */
    public function successes(): array
    {
        return array_filter($this->results, fn (ConversionResult $r) => $r->successful());
    }

    /** @return array<array-key, ConversionResult> */
    public function failures(): array
    {
        return array_filter($this->results, fn (ConversionResult $r) => $r->failed());
    }

    public function count(): int
    {
        return count($this->results);
    }

    public function successCount(): int
    {
        return count($this->successes());
    }

    public function failureCount(): int
    {
        return count($this->failures());
    }

    public function allSucceeded(): bool
    {
        return $this->failureCount() === 0;
    }

    /** Values keyed as supplied. Failed items are omitted, never given a fabricated value. */
    public function values(): array
    {
        return array_map(fn (ConversionResult $r) => $r->value, $this->successes());
    }

    public function toArray(): array
    {
        return [
            'total'     => $this->count(),
            'succeeded' => $this->successCount(),
            'failed'    => $this->failureCount(),
            'items'     => array_map(fn (ConversionResult $r) => $r->toArray(), $this->results),
        ];
    }
}
