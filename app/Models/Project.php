<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
    ];

    protected $casts = [
        'budget'   => 'decimal:2',
        'due_date' => 'date',
        'status'   => 'integer',
        'progress' => 'integer',
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
}
