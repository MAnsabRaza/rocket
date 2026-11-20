<?php

namespace App\Services\UnitConversion;

use App\Services\UnitConversionService;

/**
 * Batch, chained and single conversion with honest result reporting (M2).
 *
 * Validation happens through UnitResolver before the service is called, so an
 * unknown category or unit is reported as a result rather than an exception.
 * The service itself throws for a route configuration forbids, and convertOne
 * catches that: a batch must report a failed item, never abandon the request.
 */
final class BatchConverter
{
    public const DEFAULT_MAX_BATCH_SIZE = 500;

    public function __construct(
        private readonly UnitConversionService $service,
        private readonly UnitResolver $resolver,
    ) {}

    /**
     * @param  iterable<array-key, array{value: float|int|string, category: string, from: string, to: string}> $items
     * @throws Exceptions\UnitConversionException when the batch exceeds the permitted size
     */
    public function convertBatch(iterable $items, ?int $maxSize = null): BatchResult
    {
        $max   = $maxSize ?? (int) config('units.max_batch_size', self::DEFAULT_MAX_BATCH_SIZE);
        $items = is_array($items) ? $items : iterator_to_array($items);
        $count = count($items);

        if ($count > $max) {
            throw new Exceptions\UnitConversionException(
                "Batch size {$count} exceeds the permitted maximum of {$max}."
            );
        }

        $results = [];
        foreach ($items as $key => $item) {
            $results[$key] = $this->convertOne(
                (float) ($item['value'] ?? 0),
                (string) ($item['category'] ?? ''),
                (string) ($item['from'] ?? ''),
                (string) ($item['to'] ?? ''),
            );
        }

        return new BatchResult($results);
    }

    /** Convert through an explicit sequence of units, validating each hop. */
    public function convertChain(float $value, string $category, array $path): ConversionResult
    {
        if (count($path) < 2) {
            return ConversionResult::failure(
                ConversionOutcome::IncompatibleUnits, $value, $category,
                (string) ($path[0] ?? ''), (string) ($path[count($path) - 1] ?? ''),
                'A conversion chain requires at least two units.'
            );
        }

        $current = $value;
        $from    = (string) $path[0];
        $to      = (string) $path[count($path) - 1];

        for ($i = 0; $i < count($path) - 1; $i++) {
            $step = $this->convertOne($current, $category, (string) $path[$i], (string) $path[$i + 1]);

            if ($step->failed()) {
                return ConversionResult::failure(
                    $step->outcome, $value, $category, $from, $to,
                    sprintf('Chain failed at step %d (%s to %s): %s',
                        $i + 1, $path[$i], $path[$i + 1], $step->message)
                );
            }

            $current = $step->value;
        }

        return ConversionResult::success(
            $current, $value, $category, $from, $to, $this->resolver->precisionFor($category)
        );
    }

    /** Single conversion returning an honest result object, with alias resolution. */
    public function convertOne(float $value, string $category, string $from, string $to): ConversionResult
    {
        if (! $this->resolver->categoryExists($category)) {
            return ConversionResult::failure(
                ConversionOutcome::UnknownCategory, $value, $category, $from, $to,
                "Unknown unit category '{$category}'."
            );
        }

        $canonicalFrom = $this->resolver->resolveUnit($category, $from);
        $canonicalTo   = $this->resolver->resolveUnit($category, $to);

        foreach ([[$from, $canonicalFrom], [$to, $canonicalTo]] as [$given, $canonical]) {
            if ($canonical === null) {
                return ConversionResult::failure(
                    ConversionOutcome::UnknownUnit, $value, $category, $from, $to,
                    "Unit '{$given}' is not defined in category '{$category}'."
                );
            }
        }

        // The service throws when configuration forbids a route. Letting that
        // escape would reach ConversionController::batch(), which catches
        // UnitConversionException and rejects the entire batch, so one bad item
        // would discard every good one.
        try {
            $converted = $this->service->convert($value, $category, $canonicalFrom, $canonicalTo);
        } catch (Exceptions\ConversionPathNotAllowedException $e) {
            return ConversionResult::failure(
                ConversionOutcome::PathNotAllowed,
                $value, $category, $canonicalFrom, $canonicalTo, $e->getMessage()
            );
        } catch (Exceptions\UnitConversionException $e) {
            return ConversionResult::failure(
                ConversionOutcome::IncompatibleUnits,
                $value, $category, $canonicalFrom, $canonicalTo, $e->getMessage()
            );
        }

        return ConversionResult::success(
            $converted,
            $value, $category, $canonicalFrom, $canonicalTo,
            $this->resolver->precisionFor($category)
        );
    }
}
