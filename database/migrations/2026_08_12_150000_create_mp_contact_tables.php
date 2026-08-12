<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function dropMpContactTablesIfExist(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('mp_contact_poll_comments');
        Schema::dropIfExists('mp_contact_poll_votes');
        Schema::dropIfExists('mp_contact_polls');
        Schema::dropIfExists('mp_contact_tickets');
        Schema::dropIfExists('mp_contact_admin_requests');
        Schema::dropIfExists('mp_contact_admins');
        Schema::enableForeignKeyConstraints();
    }

    public function up(): void
    {
        $this->dropMpContactTablesIfExist();

        Schema::create('mp_contact_admins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('chat_id', 64);
            $table->string('origin', 20)->default('telegram');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('bot_id');
            $table->unique(['bot_id', 'chat_id', 'origin'], 'mp_contact_admins_unique');
        });

        Schema::create('mp_contact_admin_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('chat_id', 64);
            $table->string('origin', 20)->default('telegram');
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['bot_id', 'status']);
            $table->index(['bot_id', 'chat_id', 'origin']);
        });

        Schema::create('mp_contact_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('chat_id', 64);
            $table->string('origin', 20)->default('telegram');
            $table->string('tracking_code', 16);
            $table->text('body');
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['bot_id', 'created_at']);
            $table->index(['bot_id', 'chat_id', 'origin']);
            $table->unique(['bot_id', 'tracking_code'], 'mp_contact_tickets_code_unique');
        });

        Schema::create('mp_contact_polls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['bot_id', 'is_active']);
        });

        Schema::create('mp_contact_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poll_id');
            $table->string('chat_id', 64);
            $table->string('origin', 20)->default('telegram');
            $table->string('choice', 20);
            $table->timestamps();

            $table->foreign('poll_id')->references('id')->on('mp_contact_polls')->onDelete('cascade');
            $table->unique(['poll_id', 'chat_id', 'origin'], 'mp_contact_poll_votes_unique');
        });

        Schema::create('mp_contact_poll_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poll_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('chat_id', 64);
            $table->string('origin', 20)->default('telegram');
            $table->text('body');
            $table->string('tracking_code', 16);
            $table->timestamps();

            $table->foreign('poll_id')->references('id')->on('mp_contact_polls')->onDelete('cascade');
            $table->index(['bot_id', 'tracking_code']);
            $table->unique(['bot_id', 'tracking_code'], 'mp_contact_poll_comments_code_unique');
        });
    }

    public function down(): void
    {
        $this->dropMpContactTablesIfExist();
    }
};
