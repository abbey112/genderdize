<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Profile;
use App\Http\Resources\ProfileResource;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class ProfileController extends Controller
{
  public function index(Request $request)
{
    $query = Profile::query();

    // FILTERING (only apply if present)

    if ($request->has('gender')) {
        $query->where('gender', $request->gender);
    }

    if ($request->has('age_group')) {
        $query->where('age_group', $request->age_group);
    }

    if ($request->has('country_id')) {
        $query->where('country_id', $request->country_id);
    }

    if ($request->has('min_age')) {
        if (!is_numeric($request->min_age)) {
            return response()->json([
                "status" => "error",
                "message" => "Invalid query parameters"
            ], 422);
        }
        $query->where('age', '>=', $request->min_age);
    }

    if ($request->has('max_age')) {
        if (!is_numeric($request->max_age)) {
            return response()->json([
                "status" => "error",
                "message" => "Invalid query parameters"
            ], 422);
        }
        $query->where('age', '<=', $request->max_age);
    }

    if ($request->has('min_gender_probability')) {
        if (!is_numeric($request->min_gender_probability)) {
            return response()->json([
                "status" => "error",
                "message" => "Invalid query parameters"
            ], 422);
        }
        $query->where('gender_probability', '>=', $request->min_gender_probability);
    }

    if ($request->has('min_country_probability')) {
        if (!is_numeric($request->min_country_probability)) {
            return response()->json([
                "status" => "error",
                "message" => "Invalid query parameters"
            ], 422);
        }
        $query->where('country_probability', '>=', $request->min_country_probability);
    }

    // SORTING

    $allowedSort = ['age', 'created_at', 'gender_probability'];
    $allowedOrder = ['asc', 'desc'];

    $sortBy = $request->query('sort_by', 'created_at');
    $order = $request->query('order', 'desc');

    if (!in_array($sortBy, $allowedSort) || !in_array($order, $allowedOrder)) {
        return response()->json([
            "status" => "error",
            "message" => "Invalid query parameters"
        ], 422);
    }

    $query->orderBy($sortBy, $order);

    //PAGINATION

    $page = (int) $request->query('page', 1);
    $limit = (int) $request->query('limit', 10);

    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 10;
    if ($limit > 50) $limit = 50;

    // IMPORTANT: clone query before modifying
    $total = (clone $query)->count();

    $data = $query
        ->skip(($page - 1) * $limit)
        ->take($limit)
        ->get();

    //RESPONSE FORMAT (EXACT)

    return response()->json([
        "status" => "success",
        "page" => $page,
        "limit" => $limit,
        "total" => $total,
        "data" => $data
    ], 200);
}

    public function show($id)
    {
        $profile = Profile::find($id);
        if (!$profile) {
            return response()->json([
                "status" => "error",
                "message" => "Profile not found"
            ], 404);
        }
        return response()->json([
            "status" => "success",
            "data" => new ProfileResource($profile)
        ], 200);
    }
  public function search(Request $request)
{
    $q = strtolower($request->query('q'));

   if (!$request->has('q') || trim($request->q) === '') {
    return response()->json([
        "status" => "error",
        "message" => "Missing query"
    ], 400);
}

    $filters = [];
    // Gender
    if (str_contains($q, 'male')) $filters['gender'] = 'male';
    if (str_contains($q, 'female')) $filters['gender'] = 'female';

    // Age group
    if (str_contains($q, 'child')) $filters['age_group'] = 'child';
    if (str_contains($q, 'teenager')) $filters['age_group'] = 'teenager';
    if (str_contains($q, 'adult')) $filters['age_group'] = 'adult';
    if (str_contains($q, 'senior')) $filters['age_group'] = 'senior';

    // Young
    if (str_contains($q, 'young')) {
        $filters['min_age'] = 16;
        $filters['max_age'] = 24;
    }

    // Above age
    if (preg_match('/above (\d+)/', $q, $matches)) {
        $filters['min_age'] = (int)$matches[1];
    }

    // Country mapping
    $countries = [
        'nigeria' => 'NG',
        'kenya' => 'KE',
        'angola' => 'AO',
        'ghana' => 'GH'
    ];

    foreach ($countries as $name => $code) {
        if (str_contains($q, $name)) {
            $filters['country_id'] = $code;
        }
    }

    if (empty($filters)) {
        return response()->json([
            "status" => "error",
            "message" => "Unable to interpret query"
        ], 422);
    }

    // Reuse index logic
    return $this->index(new Request(array_merge($filters, $request->all())));
}

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
                    "data" => new ProfileResource($existing)
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
                "data" => new ProfileResource($profile)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e ->getMessage()
            ], 500);
        }
    }
    public function destroy($id)
    {
        //
    }
    
}
