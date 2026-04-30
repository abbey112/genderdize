<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Validate the request
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
             'role' => 'analyst', // Default role is analyst
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully'
            ], 201);
    }
    public function login(Request $request)
    {
        // Validate the request
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Attempt to authenticate the user
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
                ], 401);
        }

        // Generate a new access token
        $user = Auth::user();
        $token = $user->createToken('access_token')->plainTextToken;

        $refreshToken = Str::random(64);
        DB::table('refresh_tokens')->insert([
            'user_id' => auth()->id(),
            'token' => $refreshToken,
            'expires_at' => Carbon::now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $hashedToken = hash('sha256', $request->refresh_token);

        $record = DB::table('refresh_tokens')
            ->where('token', $request->refresh_token)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$record || $record->expires_at < Carbon::now()->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired refresh token'
                ], 401);
        }

        $user = User::find($record->user_id);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
                ], 404);
        }

        // Generate a new access token
        $user = User::find($record->user_id);

        $token = $user->createToken('access_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 60 * 60, // 1 hour
        ]);
    }

    public function githubRedirect()
    {
        return Socialite::driver('github')->stateless()->redirect();
    }

    public function githubCallback()
    {
        try {
            $githubUser = Socialite::driver('github')->stateless()->user();

            $user = User::firstOrCreate(
                ['email' => $githubUser->getEmail()],
                ['name' => $githubUser->getName() ?? $githubUser->getNickname(),
                 'password' => Hash::make(Str::random(16)),
                  'role' => 'analyst']
            );

            $token = $user->createToken('access_token')->plainTextToken;
            $refreshToken = Str::random(64);
            DB::table('refresh_tokens')->insert([
                'user_id' => $user->id,
                'token' => $refreshToken,
                'expires_at' => Carbon::now()->addDays(7),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json([
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'expires_in' => 900, // 15 minutes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication failed'
                ], 500);
        }
    }

    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
            ], 200);
    }
}
