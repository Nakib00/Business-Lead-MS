<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSocialMideaLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'linkedin_link',
        'twitter_link',
        'github_link',
        'dribbble_link',
        'behance_link',
        'personal_website_link',
    ];
}
