<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Form_fild extends Model
{
    use HasFactory;

    protected $fillable = ['form_id', 'field_type', 'label', 'is_required', 'options', 'field_order'];
    protected $casts = ['options' => 'array'];
    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
