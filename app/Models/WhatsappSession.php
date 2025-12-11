<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class WhatsappSession extends Model
{
    protected $fillable = [
        'wa_id',
        'user_id',
        'step',
        'phone',

        'origin_fond',
        'relaction',
        'motif',

        'password',
        'token',

        'transfer_mode',
        'amount',

        'beneficiary',
        'beneficiaryId',

        'sender',
        'senderId',

        'country',
        'countryId',

        'city',
        'cityId',

        'operator_id',

        'expires_at'
    ];

    protected $dates = [
        'expires_at'
    ];

    // 🔥 Vérifie si la session a expiré (facultatif si tu veux auto-reset)
    public function isExpired()
    {
        return $this->expires_at && Carbon::now()->greaterThan($this->expires_at);
    }
}
