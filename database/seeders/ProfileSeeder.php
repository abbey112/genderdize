<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class ProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $data = json_decode(file_get_contents(database_path('data/profiles.json')), true);

    foreach ($data as $item) {
        DB::table('profiles')->updateOrInsert(
            ['name' => strtolower($item['name'])], 
            [
                'id' => Uuid::uuid7()->toString(),
                'gender' => $item['gender'],
                'gender_probability' => $item['gender_probability'],
                'age' => $item['age'],
                'age_group' => $item['age_group'],
                'country_id' => $item['country_id'],
                'country_name' => $item['country_name'],
                'country_probability' => $item['country_probability'],
                'created_at' => now('UTC')
            ]
        );
    }
    }
}
