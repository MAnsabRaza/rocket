<?php

namespace App\Services\UnitConversion;

enum ConversionOutcome: string
{
    case Success           = 'success';
    case UnknownCategory   = 'unknown_category';
    case UnknownUnit       = 'unknown_unit';
    case IncompatibleUnits = 'incompatible_units';
    case PathNotAllowed    = 'path_not_allowed';
}
