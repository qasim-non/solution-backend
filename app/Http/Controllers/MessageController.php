<?php

namespace App\Http\Controllers;

use App\Models\Message;

class MessageController extends Controller
{
    public function returnAllMessages()
    {
        $messages = Message::getAllMessages();

        return response()->json(['message' => 'Messages retrieved successfully.', 'messages' => $messages], 200);
    }
}
