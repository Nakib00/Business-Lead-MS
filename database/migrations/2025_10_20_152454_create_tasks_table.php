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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('task_name');
            $table->text('description')->nullable();

            $table->unsignedTinyInteger('status')->default(0)->comment('0=pending,1=in_progress,2=done,3=blocked');

            $table->date('due_date')->nullable();

            $table->enum('priority', ['low', 'medium', 'high'])->default('low');

            // Can be multiple, comma-separated (e.g. "backend,api,urgent")
            $table->string('category')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
