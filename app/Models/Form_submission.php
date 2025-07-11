<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Form_submission extends Model
{
    use HasFactory;

    protected $fillable = ['form_id', 'submitted_by'];
    public function data()
    {
        return $this->hasMany(Submission_date::class, 'submission_id');
    }
    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
