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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function username()
    {
        return 'username';
    }

    // /**
    //  * @OA\Post(
    //  *     path="/api/v1/register",
    //  *     tags={"Authentication"},
    //  *     summary="Register a new lab user",
    //  *     description="Create a new lab user account with generated username and password",
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\JsonContent(
    //  *             required={"email", "lab_id"},
    //  *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
    //  *             @OA\Property(property="lab_id", type="integer", example=1)
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=201,
    //  *         description="Registration successful",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="message", type="string", example="User registered successfully"),
    //  *             @OA\Property(
    //  *                 property="data",
    //  *                 type="object",
    //  *                 @OA\Property(property="username", type="string", example="LAB001user"),
    //  *                 @OA\Property(property="password", type="string", example="randomPassword123")
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=422,
    //  *         description="Validation error",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="message", type="string", example="The given data was invalid."),
    //  *             @OA\Property(
    //  *                 property="errors",
    //  *                 type="object",
    //  *                 @OA\Property(
    //  *                     property="email",
    //  *                     type="array",
    //  *                     @OA\Items(type="string", example="The email field is required.")
    //  *                 )
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=500,
    //  *         description="Internal server error",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=false),
    //  *             @OA\Property(property="message", type="string", example="Registration failed. Please try again."),
    //  *             @OA\Property(property="error", type="string", example="Internal server error")
    //  *         )
    //  *     )
    //  * )
    //  */
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|unique:users',
                'lab_id' => 'required|integer|exists:labs,id',
            ], [
                'lab_id.required' => 'Lab ID is required.',
                'lab_id.integer' => 'Invalid lab ID.',
                'lab_id.exists' => 'Lab ID does not exist.',
            ]);

            Log::info('User registration attempt', [
                'email' => $validated['email'],
                'lab_id' => $validated['lab_id']
            ]);

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

            Log::info('User registered successfully', [
                'user_id' => $user->id,
                'username' => $username,
                'lab_id' => $lab->id
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'username' => $username,
                    'password' => $plainPassword
                ],
                'message' => 'User registered successfully'
            ], 201);

        } catch (ValidationException $e) {
            Log::warning('Registration validation failed', [
                'errors' => $e->errors(),
                'input' => $request->only(['email', 'lab_id'])
            ]);
            throw $e;
        } catch (Throwable $e) {
            Log::error('Registration failed', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'input' => $request->only(['email', 'lab_id'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
                'error' => 'Internal server error'
            ], 500);
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
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *                 @OA\Property(property="token_type", type="string", example="bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid credentials"),
     *             @OA\Property(property="error", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="username",
     *                     type="array",
     *                     @OA\Items(type="string", example="The username field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Login failed. Please try again."),
     *             @OA\Property(property="error", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function login(APIAuthRequest $request)
    {
        try {
            $credentials = $request->only($this->username(), 'password');

            // Set JWT TTL to 30 days (43,200 minutes) before attempting login
            //Auth::guard('lab')->factory()->setTTL(43200);

            if (!$token = Auth::guard('lab')->attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'error' => 'Unauthorized'
                ], 401);
            }

            // Calculate expiration time in seconds (30 days)
            $expiresIn = 43200 * 60; // 43,200 minutes * 60 seconds = 2,592,000 seconds

            $labCredential = LabCredential::where('username', $credentials['username'])->first();

           if ($labCredential) {
                $labCredential->expires_at = now()->addSeconds($expiresIn)->timestamp;
                $labCredential->save();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => $expiresIn
                ],
                'message' => 'Login successful'
            ], 200);
        } catch (Throwable $e) {
            Log::error('Login error', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'username' => $credentials['username'] ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please try again.',
                'error' => 'Internal server error'
            ], 500);
        }
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
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully logged out")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Logout failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to logout, please try again"),
     *             @OA\Property(property="error", type="string", example="Internal server error")
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
                'success' => true,
                'message' => 'Successfully logged out'
            ], 200);
        } catch (Throwable $e) {
            Log::error('Logout failed', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to logout, please try again',
                'error' => 'Internal server error'
            ], 500);
        }
    }
}