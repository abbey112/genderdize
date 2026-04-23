<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Ramsey\Uuid\Uuid;

class ProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $json = file_get_contents(database_path('data/profiles.json'));
        $data = json_decode($json, true);

  
        foreach ($data as $item) {
            DB::table('profiles')->insert([
                'id' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
                'name' => strtolower($item['name']),
                'gender' => $item['gender'],
                'gender_probability' => $item['gender_probability'],
                'sample_size' => 0,
                'age' => $item['age'],
                'age_group' => $item['age_group'],
                'country_id' => $item['country_id'],
                'country_name' => $item['country_name'],
                'country_probability' => $item['country_probability'],
                'created_at' => now('UTC')
            ]);
        }
         $faker = Faker::create();

        $countries = [
            ['id' => 'NG', 'name' => 'Nigeria'],
            ['id' => 'KE', 'name' => 'Kenya'],
            ['id' => 'GH', 'name' => 'Ghana'],
            ['id' => 'ZA', 'name' => 'South Africa'],
            ['id' => 'EG', 'name' => 'Egypt'],
        ];

        for ($i = 0; $i < 2026; $i++) {

            $age = $faker->numberBetween(1, 90);

            $age_group = match (true) {
                $age <= 12 => 'child',
                $age <= 19 => 'teenager',
                $age <= 59 => 'adult',
                default => 'senior',
            };

            $country = $faker->randomElement($countries);

            DB::table('profiles')->insert([
                'id' => Uuid::uuid7()->toString(),
                'name' => $faker->unique()->name(),
                'gender' => $faker->randomElement(['male', 'female']),
                'gender_probability' => $faker->randomFloat(2, 0.5, 0.99),
                'sample_size' => $faker->numberBetween(50, 5000),
                'age' => $age,
                'age_group' => $age_group,
                'country_id' => $country['id'],
                'country_name' => $country['name'],
                'country_probability' => $faker->randomFloat(2, 0.5, 0.99),
                'created_at' => now('UTC'),
            ]);
        }
    }
    
}