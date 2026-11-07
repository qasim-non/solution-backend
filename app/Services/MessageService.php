<?php

namespace App\Services;

use App\Models\Message;

class MessageService
{
    protected NotificationService $notification_service;

    public function __construct(NotificationService $notification_service)
    {
        $this->notification_service = $notification_service;
    }

    public function createMessage($validateMessage)
    {
        $message = Message::create($validateMessage);

        $this->notification_service->notifyAdminsOfNewMessage($message);
    }
}
