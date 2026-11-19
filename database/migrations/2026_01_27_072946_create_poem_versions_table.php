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
        Schema::create('poem_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poem_id')->comment('شناسه شعر');
            $table->unsignedBigInteger('parent_version_id')->nullable()->comment('شناسه نسخه والد (برای versioning)');
            $table->unsignedInteger('version_number')->default(1)->comment('شماره نسخه');
            $table->unsignedBigInteger('created_by')->comment('شناسه کاربر ایجادکننده نسخه');
            $table->timestamps();
            
            $table->foreign('poem_id')->references('id')->on('poems')->onDelete('cascade');
            $table->foreign('parent_version_id')->references('id')->on('poem_versions')->onDelete('set null');
            $table->index('poem_id');
            $table->index('parent_version_id');
            $table->index('version_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poem_versions');
    }
};
