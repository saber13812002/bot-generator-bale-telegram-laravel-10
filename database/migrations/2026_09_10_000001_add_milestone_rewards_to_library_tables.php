<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book Library — milestone reward engine
 *
 * Adds reward tracking columns to library_user_subscriptions and creates
 * the symbolic (demo-only) library_discount_codes table.
 *
 * Plans: plans/library-milestone-rewards-plan.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('library_user_subscriptions')) {
            Schema::table('library_user_subscriptions', function (Blueprint $table) {
                // The number of books the user must receive ("listen") before
                // the milestone reward fires. Null = no milestone armed.
                if (!Schema::hasColumn('library_user_subscriptions', 'reward_target')) {
                    $table->unsignedInteger('reward_target')->nullable()->after('books_limit');
                }
                // Books granted at the win moment (audit).
                if (!Schema::hasColumn('library_user_subscriptions', 'reward_bonus')) {
                    $table->unsignedInteger('reward_bonus')->nullable()->after('reward_target');
                }
                // Idempotency flag: set once when the reward has fired.
                if (!Schema::hasColumn('library_user_subscriptions', 'reward_granted_at')) {
                    $table->timestamp('reward_granted_at')->nullable()->after('reward_bonus');
                }
            });
        }

        if (!Schema::hasTable('library_discount_codes')) {
            Schema::create('library_discount_codes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bot_user_id');
                $table->unsignedBigInteger('bot_id');
                $table->string('code', 40)->unique();
                $table->unsignedTinyInteger('percent')->default(100);
                // Symbolic amount shown in messages (demo only, no real money)
                $table->unsignedBigInteger('display_amount')->default(0);
                $table->string('source', 30)->default('milestone');
                $table->boolean('auto_activated')->default(true);
                $table->timestamp('activated_at')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index(['bot_user_id', 'bot_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_discount_codes');

        if (Schema::hasTable('library_user_subscriptions')) {
            Schema::table('library_user_subscriptions', function (Blueprint $table) {
                foreach (['reward_granted_at', 'reward_bonus', 'reward_target'] as $column) {
                    if (Schema::hasColumn('library_user_subscriptions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
