<?php

namespace App\Services;

use App\Repositories\CustomerRepository;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CustomerRequestService
{
    protected CustomerRepository $customer_repository;

    protected NotificationService $notification_service;

    public function __construct(
        CustomerRepository $customer_repository,
        NotificationService $notification_service,
    ) {
        $this->customer_repository = $customer_repository;
        $this->notification_service = $notification_service;
    }

    public function handelCreation($requestData)
    {
        try {
            $projectRequest = $this->customer_repository->createNewRequest($requestData);

            $this->notification_service->notifyAdminsOfNewRequest($projectRequest);

        } catch (Exception $exception) {
            Log::error('Failed to create customer request: '.$exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
                'input_data' => $requestData, // Helps you debug exactly what input broke the DB
            ]);

            throw new RuntimeException('Failed to process your request. Please try again later.');
        }
    }
}
