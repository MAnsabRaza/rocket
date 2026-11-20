<?php

namespace App\Services\UnitConversion\Exceptions;

class UnknownUnitCategoryException extends UnitConversionException
{
    public static function for(string $category): self
    {
        return new self("Unknown unit category '{$category}'.");
    }
}
