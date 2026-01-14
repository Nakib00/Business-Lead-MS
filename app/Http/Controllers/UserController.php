<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use App\Models\UserEmergencyContact;
use App\Models\SecuritySetting;
use App\Models\Prefernce;
use App\Models\Display;
use App\Models\UserSocialMideaLink;
use Exception;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    use ApiResponseTrait;

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

            $query = User::with('socialMediaLink')
                ->where('reg_user_id', $userId)
                ->whereIn('type', ['leader', 'member', 'client']);


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

    // get all clients for admin
    public function getClients(Request $request, $adminId)
    {
        try {
            // ensure admin exists
            User::findOrFail($adminId);

            $query = User::with('socialMediaLink')
                ->where('reg_user_id', $adminId)
                ->where('type', 'client');

            // Search
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%");
                });
            }

            // Pagination
            $perPage = $request->input('limit', 10);
            $users = $query->paginate($perPage)->appends($request->all());

            return $this->paginatedResponse($users->items(), $users, 'Clients retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('The admin user with the specified ID was not found.');
        } catch (Exception $e) {
            return $this->serverErrorResponse('An error occurred while fetching clients.', $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $user = User::findOrFail($id);
            $permissions = $this->getFormattedPermissions($user);

            $userData = $user->toArray();
            $userData['permissions'] = $permissions;

            return $this->successResponse($userData, 'User details retrieved successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 404);
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong: ' . $e->getMessage(), 500);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->errorResponse('Unauthorized. User not authenticated.', 401);
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

                // Social Media Links (optional)
                'social_media_link.linkedin_link' => 'nullable|string|url|max:255',
                'social_media_link.twitter_link' => 'nullable|string|url|max:255',
                'social_media_link.github_link' => 'nullable|string|url|max:255',
                'social_media_link.dribbble_link' => 'nullable|string|url|max:255',
                'social_media_link.behance_link' => 'nullable|string|url|max:255',
                'social_media_link.personal_website_link' => 'nullable|string|url|max:255',
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

            // Social Media Link
            $socialData = $request->input('social_media_link', []);
            if (is_array($socialData) && !empty(array_filter($socialData, fn($v) => $v !== null && $v !== ''))) {
                $social = \App\Models\UserSocialMideaLink::firstOrCreate([
                    'user_id' => $user->id,
                ]);

                $social->fill([
                    'linkedin_link' => $socialData['linkedin_link'] ?? $social->linkedin_link,
                    'twitter_link' => $socialData['twitter_link'] ?? $social->twitter_link,
                    'github_link' => $socialData['github_link'] ?? $social->github_link,
                    'dribbble_link' => $socialData['dribbble_link'] ?? $social->dribbble_link,
                    'behance_link' => $socialData['behance_link'] ?? $social->behance_link,
                    'personal_website_link' => $socialData['personal_website_link'] ?? $social->personal_website_link,
                ]);
                $social->save();
            }

            // Reload relations for response
            $user->load(['emergencyContact', 'securitySetting', 'preference', 'display', 'socialMediaLink']);

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
                'social_media_link' => [
                    'linkedin_link' => $user->socialMediaLink->linkedin_link ?? null,
                    'twitter_link' => $user->socialMediaLink->twitter_link ?? null,
                    'github_link' => $user->socialMediaLink->github_link ?? null,
                    'dribbble_link' => $user->socialMediaLink->dribbble_link ?? null,
                    'behance_link' => $user->socialMediaLink->behance_link ?? null,
                    'personal_website_link' => $user->socialMediaLink->personal_website_link ?? null,
                ],
            ], 'Profile updated successfully', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation error', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong: ' . $e->getMessage(), 500);
        }
    }

    public function updateProfileImage(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->errorResponse('Unauthorized. User not authenticated.', 401);
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
}
