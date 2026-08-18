<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('growth_profiles', 'day_reset_hour')) {
                $table->unsignedTinyInteger('day_reset_hour')->default(3);
            }
            if (!Schema::hasColumn('growth_profiles', 'ai_consent')) {
                $table->boolean('ai_consent')->default(false);
            }
            if (!Schema::hasColumn('growth_profiles', 'settings')) {
                $table->json('settings')->nullable();
            }
        });

        if (!Schema::hasTable('growth_profile_topics')) {
            Schema::create('growth_profile_topics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('growth_profile_id');
                $table->string('template_slug', 64);
                $table->boolean('enabled')->default(true);
                $table->string('cadence', 16)->default('daily');
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->string('custom_label')->nullable();
                $table->json('weekdays')->nullable();
                $table->timestamps();

                $table->index('growth_profile_id');
                $table->index(['growth_profile_id', 'enabled'], 'gpt_profile_enabled_idx');
                $table->unique(['growth_profile_id', 'template_slug'], 'gpt_profile_slug_unique');
                $table->foreign('growth_profile_id')
                    ->references('id')
                    ->on('growth_profiles')
                    ->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('growth_reviews')) {
            Schema::create('growth_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('growth_profile_id');
                $table->timestamp('period_start')->nullable();
                $table->timestamp('period_end')->nullable();
                $table->text('body')->nullable();
                $table->json('stats')->nullable();
                $table->timestamps();

                $table->index('growth_profile_id');
                $table->foreign('growth_profile_id')
                    ->references('id')
                    ->on('growth_profiles')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_reviews');
        Schema::dropIfExists('growth_profile_topics');

        Schema::table('growth_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('growth_profiles', 'settings')) {
                $table->dropColumn('settings');
            }
            if (Schema::hasColumn('growth_profiles', 'ai_consent')) {
                $table->dropColumn('ai_consent');
            }
            if (Schema::hasColumn('growth_profiles', 'day_reset_hour')) {
                $table->dropColumn('day_reset_hour');
            }
        });
    }
};
