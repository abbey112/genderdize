<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Profile;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class ProfileController extends Controller
{
    public function store(Request $request)
    {
        try {
            $name = $request->input('name');

            //  Validation
            if ($name === null || trim($name) === '') {
                return response()->json([
                    "status" => "error",
                    "message" => "Name is required"
                ], 400);
            }

            if (!is_string($name)) {
                return response()->json([
                    "status" => "error",
                    "message" => "Name must be a string"
                ], 422);
            }

            $name = strtolower(trim($name));

            // Idempotency check
            $existing = Profile::where('name', $name)->first();
            if ($existing) {
                return response()->json([
                    "status" => "success",
                    "message" => "Profile already exists",
                    "data" => $existing
                ], 200);
            }

            //  Call APIs
            $genderRes = Http::get('https://api.genderize.io', ['name' => $name]);
            $ageRes = Http::get('https://api.agify.io', ['name' => $name]);
            $countryRes = Http::get('https://api.nationalize.io', ['name' => $name]);

            if (!$genderRes->successful() || !$ageRes->successful() || !$countryRes->successful()) {
                return response()->json([
                    "status" => "error",
                    "message" => "External API error"
                ], 502);
            }

            $genderData = $genderRes->json();
            $ageData = $ageRes->json();
            $countryData = $countryRes->json();

            //  Edge Cases
            if ($genderData['gender'] === null || $genderData['count'] == 0) {
                return response()->json([
                    "status" => "error",
                    "message" => "No gender prediction available"
                ], 422);
            }

            if ($ageData['age'] === null) {
                return response()->json([
                    "status" => "error",
                    "message" => "No age prediction available"
                ], 422);
            }

            if (empty($countryData['country'])) {
                return response()->json([
                    "status" => "error",
                    "message" => "No country data available"
                ], 404);
            }

            // Extract + Process
            $gender = $genderData['gender'];
            $gender_probability = $genderData['probability'];
            $sample_size = $genderData['count'];

            $age = $ageData['age'];

            // Age group logic
            if ($age <= 12) $age_group = 'child';
            elseif ($age <= 19) $age_group = 'teenager';
            elseif ($age <= 59) $age_group = 'adult';
            else $age_group = 'senior';

            // Pick highest country probability
            $topCountry = collect($countryData['country'])
                ->sortByDesc('probability')
                ->first();

            $country_id = $topCountry['country_id'];
            $country_probability = $topCountry['probability'];

            //  Save to DB
            $profile = Profile::create([
               'id' => Uuid::uuid7()->toString(), 
                'name' => $name,
                'gender' => $gender,
                'gender_probability' => $gender_probability,
                'sample_size' => $sample_size,
                'age' => $age,
                'age_group' => $age_group,
                'country_id' => $country_id,
                'country_probability' => $country_probability,
                'created_at' => Carbon::now('UTC')
            ]);

            return response()->json([
                "status" => "success",
                "data" => $profile
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e ->getMessage()
            ], 500);
        }
    }
}
