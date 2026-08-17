<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('growth_responses');
        Schema::dropIfExists('growth_question_schedules');
        Schema::dropIfExists('growth_question_variants');
        Schema::dropIfExists('growth_questions');
        Schema::dropIfExists('growth_programs');
        Schema::dropIfExists('growth_profiles');
        Schema::dropIfExists('growth_template_questions');
        Schema::dropIfExists('growth_templates');
        Schema::enableForeignKeyConstraints();

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

            $table->index('growth_template_id');
            $table->unique(['growth_template_id', 'question_key'], 'gtq_template_key_unique');
            $table->foreign('growth_template_id')
                ->references('id')
                ->on('growth_templates')
                ->onDelete('cascade');
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
            $table->timestamps();

            $table->index('bot_user_id');
            $table->index('bot_id');
            $table->unique(['bot_user_id', 'bot_id'], 'growth_profiles_user_bot_unique');
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

            $table->index('growth_profile_id');
            $table->index('bot_id');
            $table->index('bot_user_id');
            $table->index('status');
            $table->foreign('growth_profile_id')
                ->references('id')
                ->on('growth_profiles')
                ->onDelete('cascade');
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

            $table->index('growth_program_id');
            $table->index('question_key');
            $table->foreign('growth_program_id')
                ->references('id')
                ->on('growth_programs')
                ->onDelete('cascade');
        });

        Schema::create('growth_question_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('growth_question_id');
            $table->text('body');
            $table->string('locale', 10)->default('fa');
            $table->string('tone', 32)->nullable();
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->timestamps();

            $table->index('growth_question_id');
            $table->foreign('growth_question_id')
                ->references('id')
                ->on('growth_questions')
                ->onDelete('cascade');
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

            $table->index('growth_question_id');
            $table->index('next_due_at');
            $table->foreign('growth_question_id')
                ->references('id')
                ->on('growth_questions')
                ->onDelete('cascade');
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

            $table->index('growth_question_id');
            $table->index('growth_question_variant_id');
            $table->index('bot_user_id');
            $table->index('bot_id');
            $table->foreign('growth_question_id')
                ->references('id')
                ->on('growth_questions')
                ->onDelete('cascade');
            $table->foreign('growth_question_variant_id')
                ->references('id')
                ->on('growth_question_variants')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('growth_responses');
        Schema::dropIfExists('growth_question_schedules');
        Schema::dropIfExists('growth_question_variants');
        Schema::dropIfExists('growth_questions');
        Schema::dropIfExists('growth_programs');
        Schema::dropIfExists('growth_profiles');
        Schema::dropIfExists('growth_template_questions');
        Schema::dropIfExists('growth_templates');
        Schema::enableForeignKeyConstraints();
    }
};
