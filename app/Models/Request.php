<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Request extends Model
{
    protected $table = 'requests';

    protected $fillable = [
        'project_name',
        'mobile',
        'description',
        'status',
    ];

    public function platforms()
    {
        return $this->hasMany(RequestSocialMedia::class);
    }

    public function systemTypes()
    {
        return $this->belongsToMany(
            SystemType::class,
            'request_types',
            'requests_id',
            'type_id'
        )->as('ignore');
    }

    public static function dashboardInfo()
    {
        $total = Request::count();

        $recent = Request::where('created_at', '>=', now()->subDay())->count();

        $pending = Request::where('status', 'pending')->count();

        $completed = Request::where('status', 'completed')->count();

        // The admin dashboard shows the NEWEST requests first.
        // The JSON key stays "oldest_requests" for backwards compatibility
        // with the existing frontend type.
        $latestRequests = Request::select('id', 'project_name', 'description', 'status', 'created_at')->latest()->take(10)->get();

        return [
            'total_requests' => $total,
            'recent_requests' => $recent,
            'pending_requests' => $pending,
            'completed_requests' => $completed,
            'oldest_requests' => $latestRequests,
        ];
    }

    public static function createNewRequest($requestInfo)
    {
        return DB::transaction(function () use ($requestInfo) {

            $requestCreated = Request::create([
                'project_name' => $requestInfo['project_name'],
                'mobile' => $requestInfo['mobile'],
                'description' => $requestInfo['description'] ?? null,
                'status' => 'pending',
            ]);

            $systemTypesData = collect($requestInfo['system_types'])->map(function ($typeId) use ($requestCreated) {
                return [
                    'requests_id' => $requestCreated->id,
                    'type_id' => $typeId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            RequestType::insert($systemTypesData);

            $socialMediaData = [];
            foreach (($requestInfo['social_media'] ?? []) as $platformId => $socialUrl) {
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

            return true;
        });
    }

    public static function requestToComplete($requestId)
    {
        Request::where('id', $requestId)->update(['status' => 'completed']);

        return true;
    }

    public static function requestToPending($requestId)
    {
        Request::where('id', $requestId)->update(['status' => 'pending']);

        return true;
    }

    public static function getAllRequests()
    {
        $requests = Request::select('id', 'project_name', 'description', 'status', 'mobile', 'created_at')->with('systemTypes:name');

        return $requests;
    }

    public static function searchByName($query, $name)
    {
        return $query->where('project_name', 'like', '%'.$name.'%');
    }

    public static function searchBySystemTypes($query, $systemTypeId)
    {
        return $query->whereIn('id', function ($query) use ($systemTypeId) {
            $query->select('requests_id')
                ->from('request_types')
                ->where('type_id', $systemTypeId);
        });
    }

    public static function searchByStatus($query, $statusType)
    {
        return $query->where('status', $statusType);
    }

    public static function searchByDate($query, $startDate, $endDate)
    {
        return $query->where('created_at', '>=', $startDate)->where('created_at', '<=', $endDate);
    }
}
