<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;


class ClassifyController extends Controller
{
     public function classify(Request $request)
    {
        try {
            $name = $request->query('name');

            // ✅ Validation
            if ($name === null || trim($name) === '') {
                return response()->json([
                    "status" => "error",
                    "message" => "Name query parameter is required"
                ], 400);
            }

            if (!is_string($name)) {
                return response()->json([
                    "status" => "error",
                    "message" => "Name must be a string"
                ], 422);
            }

            // ✅ Call Genderize API
            $response = Http::get('https://api.genderize.io', [
                'name' => $name
            ]);

            if (!$response->successful()) {
                return response()->json([
                    "status" => "error",
                    "message" => "External API error"
                ], 502);
            }

            $data = $response->json();

            // ✅ Edge case
            if ($data['gender'] === null || $data['count'] == 0) {
                return response()->json([
                    "status" => "error",
                    "message" => "No prediction available for the provided name"
                ], 422);
            }

            // ✅ Process data
            $gender = $data['gender'];
            $probability = $data['probability'];
            $sample_size = $data['count'];

            $is_confident = ($probability >= 0.7 && $sample_size >= 100);

            return response()->json([
                "status" => "success",
                "data" => [
                    "name" => $name,
                    "gender" => $gender,
                    "probability" => $probability,
                    "sample_size" => $sample_size,
                    "is_confident" => $is_confident,
                    "processed_at" => Carbon::now('UTC')->toIso8601String()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => "Internal server error"
            ], 500);
        }
    }
    
}
