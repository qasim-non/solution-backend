<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContactInfo extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void {
    $contacts = [
        ['type' => 'email', 'value' => 'yqtasheel@gmail.com'],
        ['type' => 'mobile', 'value' => '+971 54 544 5786']
    ];
    foreach ($contacts as $contact) {
        DB::table('contact_infos')->updateOrInsert(
            ['type' => $contact['type']],
            [
                'type' => $contact['type'],
                'value' => $contact['value'],
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

}
