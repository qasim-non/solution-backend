<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $messages = [
            [
                'full_name' => 'Salma Ahmed',
                'email' => 'salma@example.com',
                'text_message' => 'I would like to discuss a marketing website project.',
            ],
            [
                'full_name' => 'Omar Hassan',
                'email' => 'omar@example.com',
                'text_message' => 'We need a mobile app for our service booking flow.',
            ],
            [
                'full_name' => 'Nora Khaled',
                'email' => 'nora@example.com',
                'text_message' => 'Please share pricing for a new e-commerce platform.',
            ],
        ];

        foreach ($messages as $message) {
            DB::table('messages')->updateOrInsert(
                ['email' => $message['email']],
                [
                    'full_name' => $message['full_name'],
                    'email' => $message['email'],
                    'text_message' => $message['text_message'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
