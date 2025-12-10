<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Message extends Model
{
    protected $fillable = ['sender_id', 'phone', 'direction', 'message', 'whatsapp_message_id', 'raw'];


    protected $casts = ['raw' => 'array'];


    public function sender()
    {
        return $this->belongsTo(Sender::class);
    }
}
