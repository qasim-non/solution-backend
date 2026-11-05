<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $requests = [
            [
                'project_name' => 'Marketing Website',
                'mobile' => '01012345678',
                'description' => 'A modern marketing website for a growing retail brand.',
                'status' => 'pending',
            ],
            [
                'project_name' => 'Booking Mobile App',
                'mobile' => '01122334455',
                'description' => 'A booking app for clinics with secure user authentication.',
                'status' => 'pending',
            ],
            [
                'project_name' => 'E-commerce Platform',
                'mobile' => '01233445566',
                'description' => 'A full online store with product catalog and checkout.',
                'status' => 'pending',
            ],
        ];

        foreach ($requests as $request) {
            DB::table('requests')->updateOrInsert(
                ['project_name' => $request['project_name']],
                [
                    'project_name' => $request['project_name'],
                    'mobile' => $request['mobile'],
                    'description' => $request['description'],
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
