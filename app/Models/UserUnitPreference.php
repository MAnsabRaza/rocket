<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserUnitPreference extends Model
{
    protected $table = 'user_unit_preferences';

    protected $fillable = ['user_id', 'category', 'unit'];

    /** Legacy columns on `users`, read when no normalised row exists. Never written. */
    public const LEGACY_COLUMNS = [
        'length' => 'preferred_length_unit',
        'mass'   => 'preferred_mass_unit',
        'area'   => 'preferred_area_unit',
    ];
}
