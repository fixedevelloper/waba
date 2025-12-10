<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;


class Sender extends Model
{
    protected $fillable = ['phone', 'name', 'first_seen', 'last_seen', 'meta'];


    protected $casts = [
        'meta' => 'array',
        'first_seen' => 'datetime',
        'last_seen' => 'datetime'
    ];


    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
