<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\Request;
use App\Repositories\CustomerRepository;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CustomerRequestService
{

    protected CustomerRepository $customer_repository;

    public function __construct(CustomerRepository $customer_repository)
    {
        $this->customer_repository = $customer_repository;
    }


    public function handelCreation($requestData)
    {
        try {
                $this->customer_repository->createNewRequest($requestData);

        } catch (Exception $exception) {
                Log::error('Failed to create customer request: ' . $exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
                'input_data' => $requestData // Helps you debug exactly what input broke the DB
            ]);

            throw new RuntimeException('Failed to process your request. Please try again later.');
        }
    }
}
