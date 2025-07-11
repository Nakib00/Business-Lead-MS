<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubmissionData extends Model
{
    use HasFactory;

    protected $fillable = ['submission_id', 'field_id', 'value'];
    public function field()
    {
        return $this->belongsTo(FormField::class);
    }
}
