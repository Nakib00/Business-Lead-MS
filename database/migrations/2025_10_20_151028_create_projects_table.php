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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('project_code')->unique();                 // your "create project id" (human/uuid-like code)
            $table->integer('admin_id');
            $table->integer('client_id')->nullable();
            $table->string('project_name');
            $table->string('client_name');
            $table->text('project_description')->nullable();
            $table->string('category')->nullable();                   // e.g. "web,crm,internal"
            $table->enum('priority', ['low', 'medium', 'high'])->default('low');

            // Optional budget
            $table->decimal('budget', 15, 2)->nullable();

            // Dates & status
            $table->date('due_date')->nullable();
            $table->unsignedTinyInteger('status')->default(0)->comment('0=pending,1=active,2=completed,3=on_hold');
            $table->unsignedTinyInteger('progress')->default(0);      // 0-100

            // Media
            $table->string('project_thumbnail')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
