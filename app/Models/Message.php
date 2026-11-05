<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'messages';


    protected $fillable = [
        'full_name',
        'email',
        'text_message'
    ];


    public static function createNewMessage($message)
    {
        Message::create([
            'full_name' => $message['full_name'],
            'email' => $message['email'],
            'text_message' => $message['text_message'],
        ]);
    }

    public static function getAllMessages()
    {
        return Message::select('full_name', 'email', 'text_message', 'created_at')->get();
    }

}
