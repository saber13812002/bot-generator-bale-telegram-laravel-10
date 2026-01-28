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
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->text('detailed_description')->nullable()->after('description')->comment('توضیحات کامل و جامع ربات');
            $table->string('image_path')->nullable()->after('icon_svg')->comment('مسیر عکس در storage/public');
            $table->string('image_url')->nullable()->after('image_path')->comment('URL خارجی عکس');
            $table->json('features')->nullable()->after('image_url')->comment('لیست ویژگی‌ها به صورت JSON');
            $table->text('usage_instructions')->nullable()->after('features')->comment('دستورالعمل استفاده از ربات');
            $table->text('technical_details')->nullable()->after('usage_instructions')->comment('جزئیات فنی ربات');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->dropColumn([
                'detailed_description',
                'image_path',
                'image_url',
                'features',
                'usage_instructions',
                'technical_details',
            ]);
        });
    }
};
