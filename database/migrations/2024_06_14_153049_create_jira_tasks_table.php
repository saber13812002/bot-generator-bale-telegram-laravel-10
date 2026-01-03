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
        Schema::create('jira_tasks', function (Blueprint $table) {
            $table->id();

            $table->enum('service_type', ['jira', 'trello']);
            $table->text('description')->nullable();
            $table->integer('estimate_duration')->nullable();
            $table->json('result')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jira_tasks');
    }
};
