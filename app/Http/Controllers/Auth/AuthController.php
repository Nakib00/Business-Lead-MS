<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Exception;

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
            'reg_user_id' => 'nullable|exists:users,id',
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
            'reg_user_id' => $request->reg_user_id ?? null,
            'is_subscribe' => 0,
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

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Logout successful'
        ]);
    }

    public function index(Request $request)
    {
        $query = User::query();

        // Search by name or email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        // Sort by latest (descending order)
        $query->orderBy('created_at', 'desc');

        // Pagination
        $perPage = $request->input('limit', 10);
        $users = $query->paginate($perPage)->appends($request->all());

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Users retrieved successfully',
            'data' => $users->items(),
            'pagination' => [
                'total_rows' => $users->total(),
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total_pages' => $users->lastPage(),
            ]
        ]);
    }

    // shwo user for admin
    public function registeredUsers(Request $request, $userId)
    {
        // Verify the parent user exists
        $parentUser = User::findOrFail($userId);

        $query = User::where('reg_user_id', $userId);

        // Search by name or email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%");
            });
        }

        // Filter by user type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filter by subscription status
        if ($request->has('is_subscribe')) {
            $isSubscribe = $request->input('is_subscribe');
            $query->where('is_subscribe', $isSubscribe);
        }

        // Filter by suspension status
        if ($request->has('is_suspended')) {
            $isSuspended = $request->input('is_suspended');
            $query->where('is_suspended', $isSuspended);
        }

        // Sort by latest (descending order) by default
        $sortField = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_dir', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->input('limit', 10);
        $users = $query->paginate($perPage)->appends($request->all());

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Registered users retrieved successfully',
            'data' => $users->items(),
            'pagination' => [
                'total_rows' => $users->total(),
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total_pages' => $users->lastPage(),
            ]
        ]);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'User not found',
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'User deleted successfully',
        ]);
    }


    public function toggleSubscribe($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'User not found',
            ], 404);
        }

        $user->is_subscribe = $user->is_subscribe == 1 ? 0 : 1;
        $user->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Subscription status updated successfully',
            'data' => [
                'id' => $user->id,
                'is_subscribe' => $user->is_subscribe,
            ]
        ]);
    }

    // find total count of users and users type
    public function countUser(): JsonResponse
    {
        $totalUsers = User::count();

        $typeWiseCount = User::select('type')
            ->selectRaw('count(*) as total')
            ->groupBy('type')
            ->get()
            ->pluck('total', 'type');

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'User statistics fetched successfully',
            'data' => [
                'total_users' => $totalUsers,
                'type_wise_count' => $typeWiseCount
            ]
        ]);
    }

    // get user profile
    public function profile()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'status' => 401,
                    'message' => 'Unauthorized. User not authenticated.',
                    'data' => null,
                ], 401);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Registered user retrieved successfully',
                'data' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'profile_image' => $user->profile_image ? Storage::url($user->profile_image) : null,
                    'type' => $user->type,
                    'reg_user_id' => $user->reg_user_id,
                    'is_subscribe' => $user->is_subscribe,
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Something went wrong: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    // update user profile
    public function updateProfile(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'status' => 401,
                    'message' => 'Unauthorized. User not authenticated.',
                    'data' => null,
                ], 401);
            }

            // Validate the input
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:255',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            // Handle profile image upload
            if ($request->hasFile('profile_image')) {
                $image = $request->file('profile_image');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('profile_images'), $imageName);
                $validated['profile_image'] = 'profile_images/' . $imageName;
            }

            // Update user
            $user->update($validated);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Profile updated successfully',
                'data' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'profile_image' => $user->profile_image,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Something went wrong: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

}
