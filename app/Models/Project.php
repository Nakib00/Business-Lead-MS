<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;


class Project extends Model
{
    use HasFactory;
    protected $fillable = [
        'project_code',
        'project_name',
        'project_description',
        'category',
        'priority',
        'budget',
        'due_date',
        'status',
        'progress',
        'project_thumbnail',
        'admin_id',
        'client_id'
    ];
    protected $casts = [
        'due_date' => 'date',
    ];


    protected static function booted()
    {
        static::creating(function (Project $project) {
            if (empty($project->project_code)) {
                $project->project_code = 'PRJ-' . Str::upper(Str::random(6));
            }
        });
    }

    // Relationships
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->using(ProjectUser::class)
            ->withTimestamps();
    }

    // Helpers for CSV category field
    public function getCategoryArrayAttribute(): array
    {
        if (!$this->category) return [];
        return array_values(array_filter(array_map('trim', explode(',', $this->category))));
    }

    public function setCategoryArrayAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['category'] = implode(',', array_map('trim', $value));
        }
    }

    protected $appends = ['project_thumbnail_url'];

    public function getProjectThumbnailUrlAttribute()
    {
        $val = $this->attributes['project_thumbnail'] ?? null;

        if (!$val) {
            return asset('images/placeholders/project.png');
        }

        // If it's already a full URL, just return it
        if (Str::startsWith($val, ['http://', 'https://', '//'])) {
            return $val;
        }

        // If value already includes storage/app/public, just wrap with asset()
        if (Str::startsWith($val, ['/storage/app/public/', 'storage/app/public/'])) {
            return asset(ltrim($val, '/'));
        }

        // For anything else (like "projectThumbnails/xxx.jpg"),
        // build the URL with /storage/app/public/ in it
        return asset('storage/app/public/' . ltrim($val, '/'));
    }
}
