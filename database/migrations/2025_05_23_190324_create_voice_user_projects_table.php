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
        Schema::create('voice_user_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voice_user_id');
            $table->unsignedBigInteger('project_id');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('voice_user_id')
                  ->references('id')
                  ->on('voice_users')
                  ->onDelete('cascade');

            $table->foreign('project_id')
                  ->references('id')
                  ->on('projects')
                  ->onDelete('cascade');

            // برای جلوگیری از تکرار عضویت یک کاربر در یک پروژه
            $table->unique(['voice_user_id', 'project_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_user_projects');
    }
}; 