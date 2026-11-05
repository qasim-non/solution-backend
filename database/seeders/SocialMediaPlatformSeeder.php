<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SocialMediaPlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $platforms = ['Instagram', 'Facebook', 'TikTok', 'Youtube'];

        foreach ($platforms as $platform) {
            DB::table('social_media_platforms')->updateOrInsert(
                ['name' => $platform],
                [
                    'name' => $platform,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
