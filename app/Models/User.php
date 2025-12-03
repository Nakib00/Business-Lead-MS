<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'is_suspended',
        'profile_image',
        'type',
        'reg_user_id',
        'is_subscribe',
        'job_title',
        'department',
        'date_of_birth',
        'hire_date',
        'team',
        'bio',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    /**
     * JWT identifier.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Custom claims for JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'user' => [
                'id'            => $this->id,
                'name'          => $this->name,
                'email'         => $this->email,
                'phone'         => $this->phone,
                'profile_image' => $this->profile_image,
                'type'          => $this->type,
            ],
        ];
    }

    /**
     * Projects relationship.
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class)
            ->using(ProjectUser::class)
            ->withTimestamps();
    }

    /**
     * Tasks relationship.
     */
    public function tasks()
    {
        return $this->belongsToMany(Task::class)
            ->using(TaskUser::class)
            ->withTimestamps();
    }

    /**
     * Auto-append attributes.
     */
    protected $appends = ['profile_image_url'];

    /**
     * Accessor for profile image URL.
     */
    public function getProfileImageUrlAttribute()
    {
        $val = $this->attributes['profile_image'] ?? null;

        if (!$val) {
            return asset('images/placeholders/user.png'); // fallback
        }

        if (Str::startsWith($val, ['https://hubbackend.desklago.com/'])) {
            return $val;
        }

        return Storage::disk('public')->url($val);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships for settings / preferences
    |--------------------------------------------------------------------------
    */

    public function emergencyContact()
    {
        return $this->hasOne(UserEmergencyContact::class);
    }

    public function securitySetting()
    {
        return $this->hasOne(SecuritySetting::class);
    }

    public function preference()
    {
        // model class is Prefernce as you showed
        return $this->hasOne(Prefernce::class);
    }

    public function display()
    {
        return $this->hasOne(Display::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Model events - auto create related records on user create
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::created(function ($user) {
            // Only register user_id in these tables.
            // Other columns will use DB defaults / null.

            \App\Models\UserEmergencyContact::firstOrCreate([
                'user_id' => $user->id,
            ]);

            \App\Models\SecuritySetting::firstOrCreate([
                'user_id' => $user->id,
            ]);

            \App\Models\Prefernce::firstOrCreate([
                'user_id' => $user->id,
            ]);

            \App\Models\Display::firstOrCreate([
                'user_id' => $user->id,
            ]);
        });
    }
}
