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

    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Register a new lab credential",
     *     description="Creates a user and a lab credential using email and lab ID.",
     *     tags={"LabCredential"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "lab_id"},
     *             @OA\Property(property="email", type="string", format="email", example="labuser@example.com"),
     *             @OA\Property(property="lab_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lab credential successfully created",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Lab credential successfully created."),
     *             @OA\Property(property="username", type="string", example="LAB001use"),
     *             @OA\Property(property="password", type="string", example="randompass123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     * */
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
                'message' => 'Lab credential successfully created.',
                'username' => $username,
                'password' => $plainPassword
            ]);
        }
    }

    public function login(APIAuthRequest $request)
    {
        $credentials = $request->only($this->username(), 'password');

        if (!$token = Auth::guard('lab')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $expiresIn = Auth::guard('lab')->factory()->getTTL() * 60;

        $labCredential = LabCredential::where('username', $credentials['username'])->first();

        if ($labCredential) {
            $labCredential->expires_at = $expiresIn;
            $labCredential->save();
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $expiresIn
        ]);
    }

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
