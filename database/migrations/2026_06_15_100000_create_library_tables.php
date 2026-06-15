<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_bot_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->unique();
            $table->unsignedBigInteger('reader_bot_id')->nullable();
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->foreign('reader_bot_id')->references('id')->on('bots')->onDelete('set null');
        });

        Schema::create('library_genres', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('name');
            $table->unsignedTinyInteger('page')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->index(['bot_id', 'page', 'sort_order']);
        });

        Schema::create('library_books', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('random_eligible')->default(true);
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->index(['bot_id', 'is_active']);
        });

        Schema::create('library_book_genre', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('book_id');
            $table->unsignedBigInteger('genre_id');
            $table->timestamps();

            $table->foreign('book_id')->references('id')->on('library_books')->onDelete('cascade');
            $table->foreign('genre_id')->references('id')->on('library_genres')->onDelete('cascade');
            $table->unique(['book_id', 'genre_id']);
        });

        Schema::create('library_book_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('book_id');
            $table->string('type', 20); // audio, pdf, infographic
            $table->text('content_url')->nullable();
            $table->string('telegram_file_id')->nullable();
            $table->string('bale_file_id')->nullable();
            $table->timestamps();

            $table->foreign('book_id')->references('id')->on('library_books')->onDelete('cascade');
            $table->unique(['book_id', 'type']);
        });

        Schema::create('library_user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('plan', 20)->default('free');
            $table->unsignedInteger('books_used')->default(0);
            $table->unsignedInteger('books_limit')->default(3);
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('bot_user_id')->references('id')->on('bot_users')->onDelete('cascade');
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->unique(['bot_user_id', 'bot_id']);
        });

        Schema::create('library_user_books', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id');
            $table->unsignedBigInteger('book_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('delivered_via', 10)->default('main');
            $table->string('status', 20)->default('received');
            $table->boolean('revealed_title')->default(true);
            $table->boolean('is_random')->default(false);
            $table->timestamps();

            $table->foreign('bot_user_id')->references('id')->on('bot_users')->onDelete('cascade');
            $table->foreign('book_id')->references('id')->on('library_books')->onDelete('cascade');
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->index(['bot_user_id', 'bot_id']);
        });

        Schema::create('library_plan_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('plan', 20);
            $table->string('user_identifier')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->text('payment_info')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('admin_notes')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('bot_user_id')->references('id')->on('bot_users')->onDelete('cascade');
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->index(['bot_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_plan_requests');
        Schema::dropIfExists('library_user_books');
        Schema::dropIfExists('library_user_subscriptions');
        Schema::dropIfExists('library_book_media');
        Schema::dropIfExists('library_book_genre');
        Schema::dropIfExists('library_books');
        Schema::dropIfExists('library_genres');
        Schema::dropIfExists('library_bot_configs');
    }
};
