<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_social_midea_links', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('linkedin_link');
            $table->string('twitter_link');
            $table->string('github_link');
            $table->string('dribbble_link');
            $table->string('behance_link');
            $table->string('personal_website_link');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_social_midea_links');
    }
};
