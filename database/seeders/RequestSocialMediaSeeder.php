<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequestSocialMediaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $requestIds = DB::table('requests')->pluck('id')->all();
        $platformIds = DB::table('social_media_platforms')->pluck('id')->all();

        foreach ($requestIds as $requestId) {
            foreach (array_slice($platformIds, 0, 2) as $platformId) {
                DB::table('request_social_media')->updateOrInsert(
                    [
                        'requests_id' => $requestId,
                        'platform_id' => $platformId,
                        'url' => 'http//',
                    ],
                    [
                        'requests_id' => $requestId,
                        'platform_id' => $platformId,
                        'url' => 'http//',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
