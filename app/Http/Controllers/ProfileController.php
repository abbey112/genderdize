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

   if ($request->filled('gender')) {
    $query->where('gender', $request->gender);
}

if ($request->filled('age_group')) {
    $query->where('age_group', $request->age_group);
}

if ($request->filled('country_id')) {
    $query->where('country_id', $request->country_id);
}

if ($request->filled('min_age')) {
    $query->where('age', '>=', $request->min_age);
}

if ($request->filled('max_age')) {
    $query->where('age', '<=', $request->max_age);
}

if ($request->filled('min_gender_probability')) {
    $query->where('gender_probability', '>=', $request->min_gender_probability);
}

if ($request->filled('min_country_probability')) {
    $query->where('country_probability', '>=', $request->min_country_probability);
}

if ($request->filled('gender')) {
    $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
}
if ($request->filled('country_id')) {
    $query->whereRaw('UPPER(country_id) = ?', [strtoupper($request->country_id)]);
}
if ($request->filled('age_group')) {
    $query->whereRaw('LOWER(age_group) = ?', [strtolower($request->age_group)]);
}

    // SORTING

    $allowedSort = ['age', 'created_at', 'gender_probability'];
$allowedOrder = ['asc', 'desc'];


$sortBy = in_array($request->query('sort_by'), $allowedSort)
    ? $request->query('sort_by')
    : 'created_at';

$order = in_array($request->query('order'), $allowedOrder)
    ? $request->query('order')
    : 'desc';

if (!in_array($sortBy, $allowedSort) || !in_array($order, $allowedOrder)) {
    return response()->json([
        "status" => "error",
        "message" => "Invalid query parameters"
    ], 422);
}

$query->orderBy($sortBy, $order);

    //PAGINATION

    $page = max((int) $request->query('page', 1), 1);
    $limit = min((int) $request->query('limit', 10), 50);

    $total = (clone $query)->count();

    $data = $query
        ->skip(($page - 1) * $limit)
        ->take($limit)
        ->get();

  return response()->json([
    "status" => "success",
     "page" => $page,
     "limit" => $limit,
     "total" => $total,
    "data" => $data,
   
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
    $q = strtolower(trim($request->query('q', '')));

    if (!$q) {
        return response()->json([
            "status" => "error",
            "message" => "Missing query"
        ], 400);
    }

    $query = Profile::query();

    // gender
    if (str_contains($q, 'male')) $query->where('gender', 'male');
    if (str_contains($q, 'female')) $query->where('gender', 'female');

    // age rules
    if (str_contains($q, 'young')) {
        $query->whereBetween('age', [16, 24]);
    }

    if (str_contains($q, 'teen')) {
        $query->where('age_group', 'teenager');
    }

    if (str_contains($q, 'adult')) {
        $query->where('age_group', 'adult');
    }

    if (preg_match('/above (\d+)/', $q, $matches)) {
        $query->where('age', '>=', (int)$matches[1]);
    }

    // country
    if (str_contains($q, 'nigeria')) $query->where('country_id', 'NG');
    if (str_contains($q, 'kenya')) $query->where('country_id', 'KE');
    if (str_contains($q, 'ghana')) $query->where('country_id', 'GH');

    $data = $query->limit(50)->get();
    $page = max((int) $request->query('page', 1), 1);
    $limit = min((int) $request->query('limit', 10), 50);

    $total = (clone $query)->count();

 return response()->json([
    "status" => "success",
     "page" => $page,
     "limit" => $limit,
     "total" => $total,
    "data" => $data,
], 200);
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
