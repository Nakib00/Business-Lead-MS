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
        Schema::create('indvidual_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->integer('task_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('task_user_assigns_id')->nullable();
            $table->enum('status', ['pending', 'completed'])->default('pending');
            $table->integer('checkbox')->default(0); // 0: unchecked, 1: checked
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indvidual_tasks');
    }
};
