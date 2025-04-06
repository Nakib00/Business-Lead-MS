<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'type' => 'required|string',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $imagePath = null;

        if ($request->hasFile('profile_image')) {
            $imagePath = $request->file('profile_image')->store('UserProfile', 'public');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'address' => $request->address,
            'type' => $request->type,
            'profile_image' => $imagePath,
            'is_suspended' => 0,
        ]);

        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Registration successful',
            'data' => [
                'token' => $token,
                'admin' => $this->formatUser($user),
            ]
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'status' => 401,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if ($user->is_suspended) {
            return response()->json([
                'success' => false,
                'status' => 403,
                'message' => 'User account is suspended',
            ], 403);
        }

        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'admin' => $this->formatUser($user),
            ]
        ]);
    }

    private function formatUser($user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'fcm_token' => null,
            'auth_token' => null,
            'type' => $user->type,
            'status' => $user->is_suspended == 0 ? '1' : '0',
            'admin_image' => $user->profile_image,
            'role' => $user->type,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
