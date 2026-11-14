<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WS-E: ستون ai_settings روی bots — overrideهای هر bot برای دروازه‌ی AI.
 *
 * ساختار JSON (همه nullable؛ نبود کلید = default):
 *   {
 *     "ai_feature_flags": {"ocr": true, "llm": false, ...},  // override لایه‌ی 2
 *     "ai_test_group":    [123, 456]                          // allowlist rollout (customer/user ids)
 *   }
 *
 * additive-only: rollback نیاز به drop دارد فقط در صورت تمایل.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bots', 'ai_settings')) {
            Schema::table('bots', function (Blueprint $table) {
                $table->json('ai_settings')->nullable()->after('supported_message_template');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bots') && Schema::hasColumn('bots', 'ai_settings')) {
            Schema::table('bots', function (Blueprint $table) {
                $table->dropColumn('ai_settings');
            });
        }
    }
};
