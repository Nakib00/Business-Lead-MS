<?php

namespace App\Services;

use App\Models\User;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;

class UserRegistrationService
{
    /**
     * Handle user registration.
     *
     * @param  array  $data
     * @param  \Illuminate\Http\UploadedFile|null $profileImage
     * @return \App\Models\User
     */
    public function registerUser(array $data, $profileImage = null)
    {
        // 1. Image Handling
        $imagePath = null;
        if ($profileImage) {
            $imagePath = $profileImage->store('UserProfile', 'public');
        }

        // 2. Create User
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'type' => $data['type'],
            'profile_image' => $imagePath,
            'is_suspended' => 0,
            'reg_user_id' => $data['reg_user_id'] ?? null,
            'is_subscribe' => 0,
        ]);

        // 3. Assign permissions
        $this->assignDefaultPermissions($user);

        // 4. Trigger Email Verification Event
        event(new Registered($user));

        return $user;
    }

    /**
     * Assign default permissions based on user type.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    protected function assignDefaultPermissions(User $user)
    {
        $permissionsToInsert = [];
        $now = now();

        $permissionsConfig = [];

        if ($user->type === 'leader') {
            $permissionsConfig = [
                'true' => [
                    'form' => ['post', 'get', 'put', 'delete'],
                    'lead_submission' => ['post', 'get', 'put', 'delete'],
                    'project' => ['post', 'get', 'put'],
                    'task' => ['post', 'get', 'put'],
                    'user' => ['get', 'delete', 'post'],
                ],
                'false' => [
                    'project' => ['delete'],
                    'task' => ['delete'],
                    'user' => ['put'],
                ]
            ];
        } elseif ($user->type === 'member') {
            $permissionsConfig = [
                'true' => [
                    'lead_submission' => ['get', 'put', 'post'],
                    'project' => ['get', 'put'],
                    'task' => ['get', 'put'],
                    'user' => ['get'],
                ],
                'false' => [
                    'lead_submission' => ['delete'],
                    'project' => ['post', 'delete'],
                    'task' => ['post', 'delete'],
                    'user' => ['put', 'post', 'delete'],
                ]
            ];
        }

        // Prepare data for batch insert
        foreach ($permissionsConfig as $statusString => $features) {
            $statusBool = ($statusString === 'true');
            foreach ($features as $feature => $methods) {
                foreach (array_unique($methods) as $method) {
                    $permissionsToInsert[] = [
                        'user_id' => $user->id,
                        'feature' => $feature,
                        'api_method' => $method,
                        'status' => $statusBool,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        // Single Batch Insert
        if (!empty($permissionsToInsert)) {
            Permission::insert($permissionsToInsert);
        }
    }
}
