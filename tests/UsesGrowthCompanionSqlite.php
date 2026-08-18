<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;

trait UsesGrowthCompanionSqlite
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.foreign_key_constraints', false);
        $app->make('db')->purge();

        return $app;
    }

    protected function setUpGrowthCompanionTables(): void
    {
        Schema::dropIfExists('growth_reviews');
        Schema::dropIfExists('growth_profile_topics');
        Schema::dropIfExists('growth_responses');
        Schema::dropIfExists('growth_question_schedules');
        Schema::dropIfExists('growth_question_variants');
        Schema::dropIfExists('growth_questions');
        Schema::dropIfExists('growth_programs');
        Schema::dropIfExists('growth_profiles');
        Schema::dropIfExists('growth_template_questions');
        Schema::dropIfExists('growth_templates');
        Schema::dropIfExists('bot_user_states');
        Schema::dropIfExists('bot_users');
        Schema::dropIfExists('bots');

        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_mother_id')->nullable();
            $table->string('endpoint_id')->nullable();
            $table->string('language_code')->nullable();
            $table->string('bale_bot_token')->nullable();
            $table->string('telegram_bot_token')->nullable();
            $table->timestamps();
        });

        Schema::create('bot_users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('status')->default('active');
            $table->string('origin');
            $table->timestamps();
        });

        Schema::create('bot_user_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id');
            $table->integer('bot_mother_id');
            $table->string('state', 50);
            $table->json('data')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('growth_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('default_tone', 32)->nullable();
            $table->text('ai_instructions')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('growth_template_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('growth_template_id');
            $table->string('question_key', 80);
            $table->string('intent', 120)->nullable();
            $table->string('domain', 64)->nullable();
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->string('default_frequency', 16)->default('daily');
            $table->json('variants')->nullable();
            $table->timestamps();
        });

        Schema::create('growth_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('mode', 16)->default('simple');
            $table->string('tone', 32)->nullable();
            $table->string('timezone', 64)->default('Asia/Tehran');
            $table->time('notify_time')->default('21:00:00');
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->string('depth', 16)->default('quick');
            $table->string('intensity', 16)->default('balanced');
            $table->unsignedTinyInteger('interaction_budget_per_day')->default(1);
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->unsignedTinyInteger('day_reset_hour')->default(3);
            $table->boolean('ai_consent')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('growth_programs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('growth_profile_id');
            $table->unsignedBigInteger('bot_id');
            $table->unsignedBigInteger('bot_user_id');
            $table->string('name');
            $table->string('template_slug', 64)->nullable();
            $table->string('status', 16)->default('active');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('growth_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('growth_program_id');
            $table->string('question_key', 80);
            $table->string('intent', 120)->nullable();
            $table->string('domain', 64)->nullable();
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->string('frequency', 16)->default('daily');
            $table->timestamp('paused_at')->nullable();
            $table->string('source', 16)->default('template');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('growth_question_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('growth_question_id');
            $table->text('body');
            $table->string('locale', 10)->default('fa');
            $table->string('tone', 32)->nullable();
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->timestamps();
        });

        Schema::create('growth_question_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('growth_question_id');
            $table->string('cadence_type', 16)->default('daily');
            $table->json('days_of_week')->nullable();
            $table->time('time_local')->default('21:00:00');
            $table->timestamp('next_due_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('growth_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('growth_question_id');
            $table->unsignedBigInteger('growth_question_variant_id')->nullable();
            $table->unsignedBigInteger('bot_user_id');
            $table->unsignedBigInteger('bot_id');
            $table->text('body');
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
        });

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
        });

        Schema::create('growth_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('growth_profile_id');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->text('body')->nullable();
            $table->json('stats')->nullable();
            $table->timestamps();
        });
    }
}
