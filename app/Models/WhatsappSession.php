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
        'cities',
        'city',
        'cityId',
        'beneficiaries',
        'senders',
        'relations',
        'origins',
        'motifs',
        'operators',
        'operator_id',
        'beneficiary_type',
        'sender_type',
        'mode_step',
        'accountNumber',
        'swiftCode',
        'expires_at'
    ];

    protected $dates = [
        'expires_at'
    ];
    protected $casts = [
        'expires_at'=>'datetime',
        'senders' => 'array',
        'beneficiaries' => 'array',
    ];

    // 🔥 Vérifie si la session a expiré (facultatif si tu veux auto-reset)
    public function isExpired()
    {
        return $this->expires_at && Carbon::now()->greaterThan($this->expires_at);
    }
}
