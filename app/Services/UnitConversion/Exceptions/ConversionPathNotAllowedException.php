<?php

namespace App\Services\UnitConversion\Exceptions;

/**
 * Raised when both units are valid but the configuration does not permit
 * converting between them. Distinct from an unknown unit: the unit exists,
 * the route does not.
 */
class ConversionPathNotAllowedException extends UnitConversionException
{
    public static function for(string $from, string $to, string $category): self
    {
        return new self("Conversion from '{$from}' to '{$to}' is not permitted in category '{$category}'.");
    }
}
