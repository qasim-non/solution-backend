<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = ['Web', 'Mobile App', 'desktop'];

        foreach ($types as $type) {
            DB::table('system_types')->updateOrInsert(
                ['name' => $type],
                [
                    'name' => $type,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
