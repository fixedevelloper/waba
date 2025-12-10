<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WhatsappTemplate extends Model
{
    use HasFactory;

    // Champs autorisés en assignation de masse
    protected $fillable = [
        'user_id',
        'name',
        'category',
        'language',
        'body',
        'status'
    ];

    /**
     * Relation avec l'utilisateur propriétaire du template
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec les messages envoyés utilisant ce template
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'template_id');
    }

    /**
     * Vérifie si le template est approuvé
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
