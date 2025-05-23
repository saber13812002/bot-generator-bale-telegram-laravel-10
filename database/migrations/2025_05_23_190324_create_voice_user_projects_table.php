<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_user_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('voice_user_id');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('project_id')
                  ->references('id')
                  ->on('projects')
                  ->onDelete('cascade');

            $table->foreign('voice_user_id')
                  ->references('id')
                  ->on('voice_users')
                  ->onDelete('cascade');

            $table->unique(['voice_user_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_user_projects');
    }
}; 