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
        'client_name',
        'project_description',
        'category',
        'priority',
        'budget',
        'due_date',
        'status',
        'progress',
        'project_thumbnail',
        'admin_id'
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


        if (Str::startsWith($val, ['http://', 'https://', '//'])) {
            return $val;
        }


        if (Str::startsWith($val, ['/storage/'])) {
            return asset(ltrim($val, '/'));
        }

        if (Str::contains($val, 'storage/app/public/')) {
            $rel = Str::after($val, 'storage/app/public/');
            return asset('storage/' . ltrim($rel, '/'));
        }

        return Storage::disk('public')->url($val);
    }
}
