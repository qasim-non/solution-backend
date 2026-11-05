<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestSocialMedia extends Model
{
    protected $table = 'request_social_media';


    public function platform()
    {
        return $this->belongsTo(SocialMediaPlatform::class, 'platform_id');
    }

    public static function returnSocialMediaAccounts($requestId)
    {
    return RequestSocialMedia::select('platform_id', 'url')
        ->where('requests_id', $requestId)
        ->with('platform:id,name') // Grabs ONLY id and name from the platforms table
        ->get();
    }
}
