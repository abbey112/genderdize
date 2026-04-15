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
        $headers = ['Access-Control-Allow-Origin' => '*'];

        // Validation: Change to 400 for missing/empty name to match test expectations
        if (!$request->has('name') || trim($name) === '') {
            return response()->json([
                "status" => "error",
                "message" => "Name query parameter is required"
            ], 400, $headers);
        }

        if (!is_string($name)) {
            return response()->json([
                "status" => "error",
                "message" => "Name must be a string"
            ], 400, $headers);
        }

        
            // Call Genderize API: Add timeout and check for empty response to handle failures better
            $response = Http::timeout(10)->get('https://api.genderize.io', [
                'name' => $name
            ]);

            if (!$response->successful() || empty($response->json())) {
                return response()->json([
                    "status" => "error",
                    "message" => "External API error"
                ], 502, $headers);
            }

            $data = $response->json();

            // Edge case: Ensure fields are checked correctly
            if (!isset($data['gender']) || $data['gender'] === null || !isset($data['count']) || $data['count'] == 0) {
                return response()->json([
                    "status" => "error",
                    "message" => "No prediction available for the provided name"
                ], 400, $headers);
            }

            // Process data: Ensure types and defaults
            $gender = $data['gender'] ?? '';
            $probability = (float) ($data['probability'] ?? 0);
            $sample_size = (int) ($data['count'] ?? 0);

            $is_confident = ($probability >= 0.7 && $sample_size >= 100);

            // Success response: Ensure all fields are present and status is 'success'
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
            ], 200, $headers);

        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => "Internal server error"
            ], 500, $headers);
        }
    }
}
