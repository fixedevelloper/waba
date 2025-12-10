<?php


namespace App\Services;

use App\Models\Contact;
use Carbon\Carbon;

class ContactService
{
    public function registerInteraction($phone)
    {
        return Contact::updateOrCreate(
            ['phone' => $phone],
            ['last_interaction_at' => now()]
        );
    }

    public function canSendSessionMessage($phone)
    {
        $contact = Contact::where('phone', $phone)->first();
        if (!$contact || !$contact->last_interaction_at) return false;

        return $contact->last_interaction_at->gt(now()->subHours(24));
    }
}
