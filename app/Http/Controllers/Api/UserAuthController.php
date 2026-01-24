<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Mail\OtpMail;
use Illuminate\Support\Str;

class UserAuthController extends Controller
{
    /**
     * Generate a random 6-digit OTP
     */
    private function generateOtp()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get cache key for OTP
     */
    private function getOtpCacheKey($email)
    {
        return "otp_{$email}";
    }

    /**
     * Get cache key for OTP attempts (rate limiting)
     */
    private function getOtpAttemptsCacheKey($email)
    {
        return "otp_attempts_{$email}";
    }

    /**
     * Send OTP to user's email
     */
    public function sendOtp(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile_number' => 'required|digits:10',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        // Rate limiting: Check if user has exceeded OTP request attempts
        $attemptsKey = $this->getOtpAttemptsCacheKey($request->email);
        $attempts = Cache::get($attemptsKey, 0);

        if ($attempts >= 5) {
            return response()->json([
                'message' => 'Too many OTP requests. Please try again after 1 hour.'
            ], 429);
        }

        // Generate OTP
        $otp = $this->generateOtp();
        $cacheKey = $this->getOtpCacheKey($request->email);

        // Store OTP in cache with 10-minute expiry
        Cache::put($cacheKey, $otp, now()->addMinutes(10));

        // Increment attempts counter (1 hour expiry)
        Cache::put($attemptsKey, $attempts + 1, now()->addHour());

        // Send OTP via email
        try {
            Log::info("Attempting to send OTP email", [
                'email' => $request->email,
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.' . config('mail.default') . '.host'),
            ]);

            Mail::to($request->email)->send(new OtpMail($otp, $request->name, $request->email));

            Log::info("OTP sent successfully", [
                'email' => $request->email,
                'otp' => $otp,
            ]);

            // Store user data temporarily in cache (will be used after OTP verification)
            $userDataKey = "user_data_{$request->email}";
            Cache::put($userDataKey, [
                'name' => $request->name,
                'email' => $request->email,
                'mobile_number' => $request->mobile_number,
                'password' => $request->password,
            ], now()->addMinutes(10));

            return response()->json([
                'message' => 'OTP sent to your email',
                'email' => $request->email
            ], 200);

        } catch (\Exception $e) {
            Log::error("Failed to send OTP email", [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to send OTP. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first()
            ], 422);
        }

        $cacheKey = $this->getOtpCacheKey($request->email);
        $storedOtp = Cache::get($cacheKey);

        // Check if OTP exists and matches
        if (!$storedOtp || $storedOtp !== $request->otp) {
            return response()->json([
                'message' => 'Invalid or expired OTP'
            ], 422);
        }

        // Check if user already exists
        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'message' => 'Email already registered'
            ], 422);
        }

        // Retrieve user data from cache
        $userDataKey = "user_data_{$request->email}";
        $userData = Cache::get($userDataKey);

        if (!$userData) {
            return response()->json([
                'message' => 'Session expired. Please sign up again.'
            ], 422);
        }

        try {
            // Create user
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'email_verified_at' => now(),
                'password' => Hash::make($userData['password']),
                'role' => 'user',
                'hotel_id' => null, 
                'status' => 1, 
                'remember_token' => Str::random(80),
            ]);

            // Clear cache keys
            Cache::forget($cacheKey);
            Cache::forget($userDataKey);

            return response()->json([
                'message' => 'Registration successful! Please log in.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Rate limiting: Check if user has exceeded OTP request attempts
        $attemptsKey = $this->getOtpAttemptsCacheKey($request->email);
        $attempts = Cache::get($attemptsKey, 0);

        if ($attempts >= 5) {
            return response()->json([
                'message' => 'Too many OTP requests. Please try again after 1 hour.'
            ], 429);
        }

        // Check if user data exists in cache
        $userDataKey = "user_data_{$request->email}";
        $userData = Cache::get($userDataKey);

        if (!$userData) {
            return response()->json([
                'message' => 'No pending registration found. Please sign up again.'
            ], 422);
        }

        // Generate new OTP
        $otp = $this->generateOtp();
        $cacheKey = $this->getOtpCacheKey($request->email);

        // Store OTP in cache with 10-minute expiry
        Cache::put($cacheKey, $otp, now()->addMinutes(10));

        // Increment attempts counter
        Cache::put($attemptsKey, $attempts + 1, now()->addHour());

        // Send OTP via email
        try {
            Log::info("Attempting to resend OTP email", [
                'email' => $request->email,
                'mailer' => config('mail.default'),
            ]);

            Mail::to($request->email)->send(new OtpMail($otp, $userData['name'], $request->email));

            Log::info("OTP resent successfully", [
                'email' => $request->email,
                'otp' => $otp,
            ]);

            return response()->json([
                'message' => 'OTP resent to your email'
            ], 200);

        } catch (\Exception $e) {
            Log::error("Failed to resend OTP email", [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to resend OTP. Please try again.'
            ], 500);
        }
    }

    /**
     * User Login
     */
    public function login(Request $request)
    {
        // Validate request
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Find user by email
        $user = User::where('email', $request->email)->first();

        // Check if user exists
        if (!$user) {
            Log::warning("Login attempt with non-existent email", [
                'email' => $request->email,
            ]);

            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check password
        if (!Hash::check($request->password, $user->password)) {
            Log::warning("Login attempt with incorrect password", [
                'email' => $request->email,
            ]);

            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if email is verified
        // if (!$user->email_verified_at) {
        //     Log::warning("Login attempt with unverified email", [
        //         'email' => $request->email,
        //     ]);

        //     return response()->json([
        //         'message' => 'Please verify your email before logging in.'
        //     ], 403);
        // }

        // Check if account is active
        if ($user->status == 0) {
            Log::warning("Login attempt on inactive account", [
                'email' => $request->email,
            ]);

            return response()->json([
                'message' => 'Your account is inactive. Please contact support.'
            ], 403);
        }

        // Verify user role is 'user'
        if ($user->role !== 'user') {
            Log::warning("Invalid role attempting user login", [
                'email' => $request->email,
                'role' => $user->role,
            ]);

            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Delete existing tokens to force single session
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('user_token')->plainTextToken;

        Log::info("User logged in successfully", [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json([
            'token' => $token,
            'role' => $user->role,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ], 200);
    }
}
