<?php

namespace App\Services\UnitConversion;

/**
 * Standardised result object required by M2.
 *
 * Replaces the bare float return, which could not distinguish a successful
 * conversion from a failure that silently handed back the input value.
 */
final class ConversionResult
{
    private function __construct(
        public readonly ConversionOutcome $outcome,
        public readonly ?float $value,
        public readonly float $input,
        public readonly string $category,
        public readonly string $fromUnit,
        public readonly string $toUnit,
        public readonly ?int $precision = null,
        public readonly ?string $message = null,
    ) {}

    public static function success(
        float $value, float $input, string $category,
        string $from, string $to, int $precision
    ): self {
        return new self(ConversionOutcome::Success, $value, $input, $category, $from, $to, $precision);
    }

    public static function failure(
        ConversionOutcome $outcome, float $input, string $category,
        string $from, string $to, string $message
    ): self {
        return new self($outcome, null, $input, $category, $from, $to, null, $message);
    }

    public function successful(): bool
    {
        return $this->outcome === ConversionOutcome::Success;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    /** Value on success, or the supplied fallback. Never silently returns the input. */
    public function valueOr(?float $fallback = null): ?float
    {
        return $this->successful() ? $this->value : $fallback;
    }

    /** Value on success, exception on failure. */
    public function valueOrFail(): float
    {
        if ($this->failed()) {
            throw new Exceptions\UnitConversionException($this->message ?? 'Conversion failed.');
        }

        return $this->value;
    }

    public function toArray(): array
    {
        return [
            'outcome'   => $this->outcome->value,
            'value'     => $this->value,
            'input'     => $this->input,
            'category'  => $this->category,
            'from_unit' => $this->fromUnit,
            'to_unit'   => $this->toUnit,
            'precision' => $this->precision,
            'message'   => $this->message,
        ];
    }
}
