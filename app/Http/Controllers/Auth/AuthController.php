<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Exception;
use App\Traits\ApiResponseTrait;

class AuthController extends Controller
{
    use ApiResponseTrait;
    public function register(Request $request)
    {
        try {
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
                return $this->errorResponse('Validation error', $validator->errors()->first(), 422);

            }

            $imagePathDB = null;

            if ($request->hasFile('profile_image')) {
                $imagePath = $request->file('profile_image')->store('UserProfile', 'public');
                $imagePathDB = env('APP_URL') . '/storage/app/public/' . $imagePath;
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'address' => $request->address,
                'type' => $request->type,
                'profile_image' => $imagePathDB,
                'is_suspended' => 0,
                'reg_user_id' => $request->reg_user_id ?? null,
                'is_subscribe' => 0,
            ]);

            $token = JWTAuth::fromUser($user);

            $data = [
                'token' => $token,
                'user' => $this->formatUser($user),
            ];

            return $this->successResponse($data, 'Registration successful', 201);
        } catch (Exception $e) {
            \Log::error('Register Error: ' . $e->getMessage());

            return $this->errorResponse('Registration failed', $e->getMessage(), 500);
        }
    }


    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return $this->errorResponse('Invalid credentials', 401);
        }

        $user = Auth::user();

        if ($user->is_suspended) {
            return $this->errorResponse('Your account is suspended. Please contact support.', 403);
        }

        $data = [
            'token' => $token,
            'user' => $this->formatUser($user),
        ];
        return $this->successResponse($data, 'Login successful', 200);
    }

    private function formatUser($user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'type' => $user->type,
            'profile_image' => $user->profile_image,
            'is_subscribe' => $user->is_subscribe,
            'is_suspended' => $user->is_suspended,
        ];
    }
    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return $this->successResponse(null, 'Logout successful', 200);
        } catch (Exception $e) {
            return $this->errorResponse('Error during logout', $e->getMessage(), 500);
        }
    }

    public function index(Request $request)
    {
        try {
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

            // Use the paginatedResponse method from the trait
            return $this->paginatedResponse($users->items(), $users, 'Users retrieved successfully');

        } catch (Exception $e) {
            // Use the serverErrorResponse for any unexpected errors
            return $this->serverErrorResponse('An error occurred while fetching users.', $e->getMessage());
        }
    }


    // get all admins
    public function getAdmins(Request $request)
    {
        $query = User::query()->where('type', 'admin');

        // Optional: Search by name, email, or phone
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%");
            });
        }

        // Pagination
        $perPage = $request->input('limit', 10); // Default 10
        $currentPage = $request->input('page', 1); // Default 1

        $pagination = $query->paginate($perPage, ['*'], 'page', $currentPage);
        $data = $pagination->items();

        return $this->paginatedResponse($data, $pagination, 'Admin users retrieved successfully');
    }

    // show user for admin
    public function registeredUsers(Request $request, $userId)
    {
        try {
            // Verify the parent user exists, will throw an exception if not found
            User::findOrFail($userId);

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

            // Use the paginatedResponse method from the trait
            return $this->paginatedResponse($users->items(), $users, 'Registered users retrieved successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('The parent user with the specified ID was not found.');
        } catch (Exception $e) {
            // Use the serverErrorResponse for any other unexpected errors
            return $this->serverErrorResponse('An error occurred while fetching registered users.', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        $user->delete();

        return $this->successResponse(null, 'User deleted successfully', 200);
    }


    public function toggleSubscribe($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        $user->is_subscribe = $user->is_subscribe == 1 ? 0 : 1;
        $user->save();

        $data = [
            'id' => $user->id,
            'is_subscribe' => $user->is_subscribe,
        ];
        return $this->successResponse($data, 'Subscription status updated successfully', 200);
    }

    // find total count of users and users type
    public function countUser()
    {
        $totalUsers = User::count();

        $typeWiseCount = User::select('type')
            ->selectRaw('count(*) as total')
            ->groupBy('type')
            ->get()
            ->pluck('total', 'type');


        $data = [
            'total_users' => $totalUsers,
            'type_wise_count' => $typeWiseCount
        ];
        return $this->successResponse($data, 'User statistics fetched successfully', 200);
    }

    // get user profile
    public function profile()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->errorResponse('Unauthorized. User not authenticated.', 401);
            }

            $data = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'profile_image' => $user->profile_image ? Storage::url($user->profile_image) : null,
                'type' => $user->type,
                'reg_user_id' => $user->reg_user_id,
                'is_subscribe' => $user->is_subscribe,
            ];

            return $this->successResponse($data, 'User profile retrieved successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong: ' . $e->getMessage(), 500);
        }
    }

    // update user profile
    public function updateProfile(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->errorResponse('Unauthorized. User not authenticated.', 401);
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


            $data = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'profile_image' => $user->profile_image,
            ];
            return $this->successResponse($data, 'Profile updated successfully', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong: ' . $e->getMessage(), 500);
        }
    }

    // change password
    public function changePassword(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->errorResponse('Unauthorized. User not authenticated.', 401);
            }


            // Validate the input
            $validated = $request->validate([
                'current_password' => 'required|string|min:6',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            // Check if current password is correct
            if (!Hash::check($validated['current_password'], $user->password)) {
                return $this->errorResponse('Current password is incorrect', 422);
            }

            // Update password
            $user->password = Hash::make($validated['new_password']);
            $user->save();

            return $this->successResponse(null, 'Password changed successfully', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong: ' . $e->getMessage(), 500);
        }
    }
}
