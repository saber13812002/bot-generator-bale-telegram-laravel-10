<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            if (!Schema::hasColumn('webhook_endpoints', 'wizard_steps')) {
                $table->json('wizard_steps')
                    ->nullable()
                    ->comment('مراحل ویزارد ساخت ربات برای این endpoint')
                    ->after('supports_multiple_languages');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            if (Schema::hasColumn('webhook_endpoints', 'wizard_steps')) {
                $table->dropColumn('wizard_steps');
            }
        });
    }
};
