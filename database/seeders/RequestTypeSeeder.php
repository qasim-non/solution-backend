<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequestTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $requestIds = DB::table('requests')->pluck('id')->all();
        $typeIds = DB::table('system_types')->pluck('id')->all();

        foreach ($requestIds as $requestId) {
            foreach (array_slice($typeIds, 0, 2) as $typeId) {
                DB::table('request_types')->updateOrInsert(
                    [
                        'requests_id' => $requestId,
                        'type_id' => $typeId,
                    ],
                    [
                        'requests_id' => $requestId,
                        'type_id' => $typeId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
