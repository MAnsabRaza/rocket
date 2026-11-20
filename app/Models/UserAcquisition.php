<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAcquisition extends Model
{
    protected $table = 'user_acquisitions';
    protected $guarded = ['id'];
    protected $casts = ['captured_at' => 'datetime'];
}
