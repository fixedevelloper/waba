<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = ['phone', 'last_interaction_at'];
    protected $dates = ['last_interaction_at'];
    protected $casts = [
        'last_interaction_at' => 'datetime',
    ];

}
