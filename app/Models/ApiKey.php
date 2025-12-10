<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class ApiKey extends Model
{
    protected $fillable = ['name','key','quota','used','expires_at'];


    protected $dates = ['expires_at'];
    protected $casts = [
        'expires_at' => 'datetime',
    ];


    public function isValid(): bool
    {
        // Si une date d'expiration existe et est passée → clé invalide
        if ($this->expires_at?->isPast()) {
        return false;
    }

    // Vérifie le quota
    return $this->used < $this->quota;
}

}
