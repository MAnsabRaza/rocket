<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFormatPreference extends Model
{
    protected $table = 'user_format_preferences';

    protected $fillable = [
        'user_id', 'measurement_system', 'locale',
        'decimal_separator', 'thousands_separator', 'display_precision',

        // Display formats, added 24 August 2026. They have no column on `users`
        // and previously survived only in a cookie, which the 502 correction
        // removed for signed-in users.
        'date_format', 'time_format', 'week_start',
    ];

    protected $casts = ['display_precision' => 'integer'];
}
