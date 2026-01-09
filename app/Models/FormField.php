<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    use HasFactory;

    protected $fillable = ['form_id', 'field_type', 'label', 'is_required', 'options', 'field_order','tooltip'];
    protected $casts = ['options' => 'array'];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function submissionData()
    {
        return $this->hasMany(SubmissionData::class, 'field_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($field) {
            // delete all submission data linked to this field
            $field->submissionData()->delete();
        });
    }

}
