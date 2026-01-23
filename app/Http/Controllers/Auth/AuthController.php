<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Exception;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Log;
use App\Services\UserRegistrationService;

class AuthController extends Controller
{
    use ApiResponseTrait;

    protected $registrationService;

    public function __construct(UserRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

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

            $user = $this->registrationService->registerUser($request->except('profile_image'), $request->file('profile_image'));

            return $this->successResponse(
                $this->formatUser($user),
                'Registration successful. Please check your email to verify your account.',
                201
            );
        } catch (Exception $e) {
            Log::error('Register Error: ' . $e->getMessage());
            return $this->errorResponse('Registration failed', 'Something went wrong during registration.', 500);
        }
    }


    public function login(Request $request)
    {
        try {
            $credentials = $request->only('email', 'password');

            $user = User::with('permissions')->where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return $this->errorResponse('Invalid email or password.', null, 401);
            }

            if ($user->is_suspended) {
                return $this->errorResponse('Your account is suspended. Please contact support.', null, 403);
            }

            if ($user->email_verified_at === null) {
                return $this->errorResponse('Your email is not verified. Please verify your email first.', null, 403);
            }

            if (!$token = JWTAuth::fromUser($user)) {
                return $this->errorResponse('Could not create token.', null, 500);
            }
            $permissions = $this->getFormattedPermissions($user);

            $data = [
                'token' => $token,
                'user' => $this->formatUser($user),
                'permissions' => $permissions,
            ];

            return $this->successResponse($data, 'Login successful', 200);
        } catch (\Exception $e) {
            Log::error('Login Error: ' . $e->getMessage());
            return $this->errorResponse('Login failed', 'An error occurred during login.', 500);
        }
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
                'display',
                'socialMediaLink'
            ]);

            $data = [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'address'       => $user->address,
                'profile_image_url' => filter_var($user->profile_image, FILTER_VALIDATE_URL)
                    ? $user->profile_image
                    : $user->profile_image_url,
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

                'social_media_link' => [
                    'linkedin_link' => $user->socialMediaLink->linkedin_link ?? null,
                    'twitter_link' => $user->socialMediaLink->twitter_link ?? null,
                    'github_link' => $user->socialMediaLink->github_link ?? null,
                    'dribbble_link' => $user->socialMediaLink->dribbble_link ?? null,
                    'behance_link' => $user->socialMediaLink->behance_link ?? null,
                    'personal_website_link' => $user->socialMediaLink->personal_website_link ?? null,
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

    private function getFormattedPermissions($user)
    {
        $permissions = [];
        foreach ($user->permissions as $permission) {
            // Convert status to boolean
            $status = filter_var($permission->status, FILTER_VALIDATE_BOOLEAN);

            if ($permission->feature && $permission->api_method) {
                $permissions[$permission->feature][$permission->api_method] = [
                    'id' => $permission->id,
                    'status' => $status
                ];
            }
        }
        return $permissions;
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
            'reg_user_id' => $user->reg_user_id,
        ];
    }
}
