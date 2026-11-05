<?php

namespace App\Repositories;

use App\Models\Request;
use App\Models\RequestSocialMedia;
use App\Models\RequestType;
use Illuminate\Support\Facades\DB;

class CustomerRepository
{
    public function createNewRequest($requestData)
    {
        return DB::transaction(function () use ($requestData) {

            $requestCreated = Request::create([
                'project_name' => $requestData['project_name'],
                'mobile' => $requestData['mobile'],
                'description' => $requestData['description'] ?? null,
                'status' => 'pending',
            ]);

            $systemTypesData = collect($requestData['system_types'])->map(function ($typeId) use ($requestCreated) {
                return [
                    'requests_id' => $requestCreated->id,
                    'type_id' => $typeId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            RequestType::insert($systemTypesData);

            $socialMediaData = [];
            foreach (($requestData['social_media'] ?? []) as $platformId => $socialUrl) {
                $socialMediaData[] = [
                    'requests_id' => $requestCreated->id,
                    'platform_id' => $platformId,
                    'url' => $socialUrl,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($socialMediaData)) {
                RequestSocialMedia::insert($socialMediaData);
            }

            return $requestCreated;
        });
    }
}
