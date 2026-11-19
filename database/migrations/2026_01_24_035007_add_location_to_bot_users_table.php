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
        Schema::table('bot_users', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('email_unsubscribe_token');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->text('location_address')->nullable()->after('longitude');
            $table->timestamp('location_set_at')->nullable()->after('location_address');
            
            $table->index(['chat_id', 'bot_id'], 'bot_users_location_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->dropIndex('bot_users_location_index');
            $table->dropColumn(['latitude', 'longitude', 'location_address', 'location_set_at']);
        });
    }
};
