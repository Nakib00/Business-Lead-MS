<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'created_user_id'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    public function assignedUsers()
    {
        return $this->hasMany(TaskUserAssign::class, 'task_id');
    }
}
