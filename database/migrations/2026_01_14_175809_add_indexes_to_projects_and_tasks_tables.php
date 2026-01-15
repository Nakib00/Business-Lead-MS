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
        Schema::table('projects', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('status');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index('project_id');
            $table->index('status');
            $table->index('priority');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['priority']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['status']);
        });
    }
};
