<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIAuthRequest;
use App\Models\Lab;
use App\Models\LabCredential;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function username()
    {
        return 'username';
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users',
            'lab_id' => 'required|integer|exists:labs,id',
        ], [
            'lab_id.required' => 'Lab ID is required.',
            'lab_id.integer' => 'Invalid lab ID.',
            'lab_id.exists' => 'Lab ID does not exist.',
        ]);

        if ($validated) {
            $lab = Lab::findOrFail($validated['lab_id']);

            do {
                $username = $lab->code .  $lab->id . get_email_abbrv($request->email);
            } while (LabCredential::where('username', $username)->exists());


            $plainPassword = Str::random(15);

            $user = User::create([
                'name' => $username,
                'email' => $request->email,
                'password' => bcrypt($plainPassword),
            ]);

            LabCredential::create([
                'user_id' => $user->id,
                'lab_id' => $lab->id,
                'username' => $username,
                'password' => bcrypt($plainPassword),
                'role' => 'lab',
                'is_active' => true,
            ]);

            return response()->json([
                'username' => $username,
                'password' => $plainPassword
            ], 200);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/login",
     *     tags={"Authentication"},
     *     summary="Login with lab credentials",
     *     description="Authenticate lab user and return JWT token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","password"},
     *             @OA\Property(property="username", type="string", example="LAB001user"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function login(APIAuthRequest $request)
    {
        $credentials = $request->only($this->username(), 'password');

        // Set JWT TTL to 30 days (43,200 minutes) before attempting login
        Auth::guard('lab')->factory()->setTTL(43200);

        if (!$token = Auth::guard('lab')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Calculate expiration time in seconds (30 days)
        $expiresIn = 43200 * 60; // 43,200 minutes * 60 seconds = 2,592,000 seconds

        $labCredential = LabCredential::where('username', $credentials['username'])->first();

        if ($labCredential) {
            $labCredential->expires_at = $expiresIn;
            $labCredential->save();
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $expiresIn
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/logout",
     *     tags={"Authentication"},
     *     summary="Logout lab user",
     *     description="Logout the authenticated lab user and invalidate the JWT token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully logged out.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Logout failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to logout, please try again.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing token"
     *     )
     * )
     */
    public function logout()
    {
        try {
            Auth::guard('lab')->logout();

            return response()->json([
                'message' => 'Successfully logged out.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to logout, please try again.'
            ], 500);
        }
    }
}
