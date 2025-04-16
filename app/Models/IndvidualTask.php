<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndvidualTask extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'description',
        'task_id',
        'user_id',
        'status',
        'checkbox',
        'task_user_assigns_id',
    ];
}
