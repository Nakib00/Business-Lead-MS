<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    use HasFactory;

    protected $fillable = ['form_id', 'submitted_by', 'admin_id', 'status'];

    public function data()
    {
        return $this->hasMany(SubmissionData::class, 'submission_id');
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($submission) {
            $submission->data()->delete();
        });
    }
}
