<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $fillable = [
        'client_id',
        'client_secret',
        'scope',
        'grant_type',
        'urls',
        'additional_info',
    ];

    protected $casts = [
        'urls' => 'array',
        'additional_info' => 'array',
    ];
}
