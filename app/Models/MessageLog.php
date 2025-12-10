<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    protected $fillable = [
        'api_key_id',
        'phone',
        'type',
        'status',
        'response',
        'error'
    ];

    protected $casts = [
        'response' => 'array', // permet de stocker et lire le JSON facilement
    ];

    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }
}
