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
        Schema::create('personnel', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('national_code', 10)->unique();
            $table->string('phone_number', 15);
            $table->unsignedBigInteger('tenant_id');
            $table->enum('rank', ['سرباز صفر', 'سرباز یک', 'سرباز دو', 'سرباز سه'])->default('سرباز صفر');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
            $table->index('national_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personnel');
    }
};
