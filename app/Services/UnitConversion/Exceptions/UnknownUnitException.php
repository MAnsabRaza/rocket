<?php

namespace App\Services\UnitConversion\Exceptions;

class UnknownUnitException extends UnitConversionException
{
    public static function for(string $unit, string $category): self
    {
        return new self("Unit '{$unit}' is not defined in category '{$category}'.");
    }
}
