<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    use HasFactory;
    protected $fillable = ['title', 'description', 'admin_id', 'created_by'];
    public function fields()
    {
        return $this->hasMany(FormField::class);
    }
    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }
}
