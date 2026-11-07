<?php

namespace App\Http\Controllers;

use App\Http\Requests\messageRequest;
use App\Http\Requests\projectRequest;
use App\Http\Resources\ContactInfoResource;
use App\Models\ContactInfo;
use App\Models\Message;
use App\Models\Request;
use App\Services\CustomerRequestService;
use App\Services\MessageService;
use Exception;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{

    public function request(projectRequest $request, CustomerRequestService $customerService)
    {
        $validateRequest = $request->validated();

        $customerService->handelCreation($validateRequest);

        return $this->success(null, 'The request was created successfully.', 201);
    }


    public function message(messageRequest $message, MessageService $messageService)
    {
        $validateMessage = $message->validated();

        $messageService->createMessage($validateMessage);

        return $this->success(null, 'The message was created successfully.', 201);
    }


    public function contact()
    {
        $contactInfo = ContactInfo::all();

        return $this->success(ContactInfoResource::collection($contactInfo), 'The contact info was gatherd successfully.', 200);
    }

}
