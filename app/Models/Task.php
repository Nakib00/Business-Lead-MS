<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'task_name',
        'description',
        'status',
        'due_date',
        'priority',
        'category',
    ];

    protected $casts = [
        'status'   => 'integer',
        'due_date' => 'date',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // Users assigned to this task (pivot)
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->using(TaskUser::class)
            ->withTimestamps();
    }

    // CSV helpers
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

    // Useful scopes
    public function scopePending($q)
    {
        return $q->where('status', 0);
    }
    public function scopeInProgress($q)
    {
        return $q->where('status', 1);
    }
    public function scopeDone($q)
    {
        return $q->where('status', 2);
    }
    public function scopeBlocked($q)
    {
        return $q->where('status', 3);
    }
    public function scopeHighPriority($q)
    {
        return $q->where('priority', 'high');
    }
}
