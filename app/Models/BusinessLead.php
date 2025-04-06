<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_name',
        'business_email',
        'business_phone',
        'business_type',
        'website_url',
        'location',
        'source_of_data',
        'status',
        'note',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
