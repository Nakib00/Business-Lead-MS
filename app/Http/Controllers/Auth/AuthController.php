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
use Illuminate\Auth\Events\Registered;
use App\Traits\ApiResponseTrait;
use App\Models\UserEmergencyContact;
use App\Models\SecuritySetting;
use App\Models\Prefernce;
use App\Models\Display;

class AuthController extends Controller
{
    use ApiResponseTrait;
    public function register(Request $request)
    {
        try {
            // 1. Validation
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

            // 2. Image Handling (Store relative path only)
            $imagePath = null;
            if ($request->hasFile('profile_image')) {
                // Stores in storage/app/public/UserProfile
                $imagePath = $request->file('profile_image')->store('UserProfile', 'public');
            }

            // 3. Create User
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'address' => $request->address,
                'type' => $request->type,
                'profile_image' => $imagePath, // Save relative path: "UserProfile/xyz.jpg"
                'is_suspended' => 0,
                'reg_user_id' => $request->reg_user_id ?? null,
                'is_subscribe' => 0,
            ]);

            // 4. Trigger Email Verification Event
            // This sends the standard Laravel verification email
            event(new Registered($user));

            // 5. Response (NO TOKEN)
            // We do not log them in yet. They must verify email first.
            return $this->successResponse(
                $this->formatUser($user),
                'Registration successful. Please check your email to verify your account.',
                201
            );
        } catch (Exception $e) {
            \Log::error('Register Error: ' . $e->getMessage());
            return $this->errorResponse('Registration failed', 'Something went wrong during registration.', 500);
        }
    }


    public function login(Request $request)
    {
        try {
            $credentials = $request->only('email', 'password');

            // 1. Retrieve the user by email first
            $user = User::where('email', $request->email)->first();

            // 2. Check if user exists and password is correct
            if (!$user || !Hash::check($request->password, $user->password)) {
                return $this->errorResponse('Invalid email or password.', null, 401);
            }

            // 3. Check if Suspended (Do this BEFORE generating token)
            if ($user->is_suspended) {
                return $this->errorResponse('Your account is suspended. Please contact support.', null, 403);
            }

            // 4. Check if Email is Verified (Do this BEFORE generating token)
            if ($user->email_verified_at === null) {
                // No token to invalidate because we haven't created one yet
                return $this->errorResponse('Your email is not verified. Please verify your email first.', null, 403);
            }

            // 5. Generate Token (Now that we know the user is valid)
            if (!$token = JWTAuth::fromUser($user)) {
                return $this->errorResponse('Could not create token.', null, 500);
            }

            // 6. Login Successful
            $data = [
                'token' => $token,
                'user' => $this->formatUser($user),
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60
            ];

            return $this->successResponse($data, 'Login successful', 200);
        } catch (\Exception $e) {
            \Log::error('Login Error: ' . $e->getMessage());
            return $this->errorResponse('Login failed', 'An error occurred during login.', 500);
        }
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

            // Load related models
            $user->load([
                'emergencyContact',
                'securitySetting',
                'preference',
                'display'
            ]);

            $data = [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'address'       => $user->address,
                'profile_image' => $user->profile_image_url,
                'type'          => $user->type,
                'reg_user_id'   => $user->reg_user_id,
                'is_subscribe'  => $user->is_subscribe,
                'bio' => $user->bio,
                'job_title' => $user->job_title,
                'department' => $user->department,
                'date_of_birth' => $user->date_of_birth,
                'hire_date' => $user->hire_date,
                'team' => $user->team,
                'timezone' => $user->timezone,

                'emergency_contact' => [
                    'name'         => $user->emergencyContact->name ?? null,
                    'relationship' => $user->emergencyContact->relationship ?? null,
                    'phone'        => $user->emergencyContact->phone ?? null,
                ],

                'security_setting' => [
                    'two_factor_auth' => $user->securitySetting->two_factor_auth ?? null,
                ],

                'preference' => [
                    'email_notifications' => $user->preference->email_notifications ?? null,
                    'sms_notifications'   => $user->preference->sms_notifications ?? null,
                    'push_notifications'  => $user->preference->push_notifications ?? null,
                ],

                'display' => [
                    'language'          => $user->display->language ?? null,
                    'theme'             => $user->display->theme ?? null,
                ],
            ];

            return $this->successResponse($data, 'User profile retrieved successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse(
                'Something went wrong: ' . $e->getMessage(),
                500
            );
        }
    }


    // update user profile
    public function updateProfile(Request $request, $userId)
    {
        try {
            $authUser = Auth::user();

            if (!$authUser) {
                return $this->errorResponse('Unauthorized. User not authenticated.', 401);
            }

            $user = User::find($userId);

            if (!$user) {
                return $this->errorResponse('User not found.', 404);
            }

            // Validate request
            $validated = $request->validate([
                'name'    => 'required|string|max:255',
                'email'   => 'required|email|unique:users,email,' . $user->id,
                'phone'   => 'nullable|string|max:20',
                'address' => 'nullable|string|max:255',

                'job_title'    => 'nullable|string|max:255',
                'department'   => 'nullable|string|max:255',
                'date_of_birth' => 'nullable|date',
                'hire_date'    => 'nullable|date',
                'team'         => 'nullable|string|max:255',
                'bio'          => 'nullable|string',
                'status'       => 'nullable|string|max:50',
                'timezone'     => 'nullable|string|max:100',

                // Emergency contact (optional)
                'emergency_contact.name'         => 'nullable|string|max:255',
                'emergency_contact.relationship' => 'nullable|string|max:255',
                'emergency_contact.phone'        => 'nullable|string|max:20',

                // Security setting (optional)
                'security_setting.two_factor_auth' => 'nullable|boolean',

                // Preference (optional)
                'preference.email_notifications' => 'nullable|boolean',
                'preference.sms_notifications'   => 'nullable|boolean',
                'preference.push_notifications'  => 'nullable|boolean',

                // Display (optional)
                'display.language' => 'nullable|string|max:10',
                'display.theme'    => 'nullable|string|max:50',
            ]);

            // 1) Update main user fields
            $user->update([
                'name'         => $validated['name'],
                'email'        => $validated['email'],
                'phone'        => $validated['phone']        ?? $user->phone,
                'address'      => $validated['address']      ?? $user->address,

                // New fields
                'job_title'    => $validated['job_title']    ?? $user->job_title,
                'department'   => $validated['department']   ?? $user->department,
                'date_of_birth' => $validated['date_of_birth'] ?? $user->date_of_birth,
                'hire_date'    => $validated['hire_date']    ?? $user->hire_date,
                'team'         => $validated['team']         ?? $user->team,
                'bio'          => $validated['bio']          ?? $user->bio,
                'status'       => $validated['status']       ?? $user->status,
                'timezone'     => $validated['timezone']     ?? $user->timezone,
            ]);

            // 2) Update / create related models (all optional)

            // Emergency contact
            $emergencyData = $request->input('emergency_contact', []);
            if (is_array($emergencyData) && !empty(array_filter($emergencyData, fn($v) => $v !== null && $v !== ''))) {
                $emergency = UserEmergencyContact::firstOrCreate([
                    'user_id' => $user->id,
                ]);

                $emergency->fill([
                    'name'         => $emergencyData['name']         ?? $emergency->name,
                    'relationship' => $emergencyData['relationship'] ?? $emergency->relationship,
                    'phone'        => $emergencyData['phone']        ?? $emergency->phone,
                ]);
                $emergency->save();
            }

            // Security setting
            $securityData = $request->input('security_setting', []);
            if (is_array($securityData) && array_key_exists('two_factor_auth', $securityData)) {
                $security = SecuritySetting::firstOrCreate([
                    'user_id' => $user->id,
                ]);

                $security->two_factor_auth = $securityData['two_factor_auth'];
                $security->save();
            }

            // Preference
            $prefData = $request->input('preference', []);
            if (is_array($prefData) && !empty(array_filter($prefData, fn($v) => $v !== null && $v !== ''))) {
                $pref = Prefernce::firstOrCreate([
                    'user_id' => $user->id,
                ]);

                $pref->fill([
                    'email_notifications' => $prefData['email_notifications'] ?? $pref->email_notifications,
                    'sms_notifications'   => $prefData['sms_notifications']   ?? $pref->sms_notifications,
                    'push_notifications'  => $prefData['push_notifications']  ?? $pref->push_notifications,
                ]);
                $pref->save();
            }

            // Display
            $displayData = $request->input('display', []);
            if (is_array($displayData) && !empty(array_filter($displayData, fn($v) => $v !== null && $v !== ''))) {
                $display = Display::firstOrCreate([
                    'user_id' => $user->id,
                ]);

                $display->fill([
                    'language' => $displayData['language'] ?? $display->language,
                    'theme'    => $displayData['theme']    ?? $display->theme,
                ]);
                $display->save();
            }

            // Reload relations for response
            $user->load(['emergencyContact', 'securitySetting', 'preference', 'display']);

            return $this->successResponse([
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'address'       => $user->address,
                'type'          => $user->type,
                'reg_user_id'   => $user->reg_user_id,
                'is_subscribe'  => $user->is_subscribe,
                'job_title'     => $user->job_title,
                'department'    => $user->department,
                'date_of_birth' => $user->date_of_birth,
                'hire_date'     => $user->hire_date,
                'team'          => $user->team,
                'bio'           => $user->bio,
                'status'        => $user->status,
                'timezone'      => $user->timezone,

                'emergency_contact' => [
                    'name'         => $user->emergencyContact->name ?? null,
                    'relationship' => $user->emergencyContact->relationship ?? null,
                    'phone'        => $user->emergencyContact->phone ?? null,
                ],
                'security_setting' => [
                    'two_factor_auth' => $user->securitySetting->two_factor_auth ?? null,
                ],
                'preference' => [
                    'email_notifications' => $user->preference->email_notifications ?? null,
                    'sms_notifications'   => $user->preference->sms_notifications ?? null,
                    'push_notifications'  => $user->preference->push_notifications ?? null,
                ],
                'display' => [
                    'language' => $user->display->language ?? null,
                    'theme'    => $user->display->theme ?? null,
                ],
            ], 'Profile updated successfully', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation error', $e->errors(), 422);
        } catch (\Exception $e) {
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

    public function updateProfileImage(Request $request, $userId)
    {
        try {
            $authUser = Auth::user();

            if (!$authUser) {
                return $this->errorResponse('Unauthorized. User not authenticated.', 401);
            }

            $user = User::find($userId);

            if (!$user) {
                return $this->errorResponse('User not found.', 404);
            }

            // Validate file
            $request->validate([
                'profile_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            ]);

            if ($request->hasFile('profile_image')) {

                // Store only the relative path
                $imagePath = $request->file('profile_image')->store('UserProfile', 'public');

                // Save only "UserProfile/xxxx.jpg"
                $user->profile_image = $imagePath;
                $user->save();
            } else {
                return $this->errorResponse('No profile image uploaded.', 422);
            }

            // Refresh to load accessor correctly
            $user->refresh();

            return $this->successResponse([
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'profile_image_url' => $user->profile_image_url,
            ], 'Profile image updated successfully', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation error', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong: ' . $e->getMessage(), 500);
        }
    }
}
