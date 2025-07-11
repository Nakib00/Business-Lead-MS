<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    use HasFactory;
    protected $fillable = ['form_id', 'submitted_by'];
    public function data()
    {
        return $this->hasMany(SubmissionData::class, 'submission_id');
    }
    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
