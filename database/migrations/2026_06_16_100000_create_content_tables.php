<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->index();
            $table->string('title');
            $table->unsignedTinyInteger('page')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['bot_id', 'page', 'sort_order']);
        });

        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->index();
            $table->unsignedBigInteger('category_id')->index();
            $table->string('title')->nullable();
            $table->unsignedInteger('queue_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['category_id', 'queue_order']);
        });

        Schema::create('content_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('content_item_id')->index();
            $table->string('type', 20)->default('audio');
            $table->text('content_url')->nullable();
            $table->string('telegram_file_id')->nullable();
            $table->string('bale_file_id')->nullable();
            $table->timestamps();
            $table->unique(['content_item_id', 'type']);
        });

        Schema::create('content_pending_uploads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->index();
            $table->string('file_id');
            $table->string('file_unique_id')->nullable();
            $table->string('origin', 20);
            $table->string('uploaded_by_chat_id');
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });

        Schema::create('content_user_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id')->index();
            $table->unsignedBigInteger('category_id')->index();
            $table->unsignedBigInteger('bot_id')->index();
            $table->unsignedInteger('last_position')->default(0);
            $table->unsignedBigInteger('last_content_item_id')->nullable();
            $table->timestamps();
            $table->unique(['bot_user_id', 'category_id']);
        });

        Schema::create('content_broadcast_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->index();
            $table->string('target_filter', 50);
            $table->unsignedBigInteger('target_category_id')->nullable();
            $table->text('message_text')->nullable();
            $table->string('file_id')->nullable();
            $table->string('file_type', 20)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('created_by_chat_id')->nullable();
            $table->timestamps();
        });

        $this->migrateFromLibraryTables();
    }

    private function migrateFromLibraryTables(): void
    {
        if (!Schema::hasTable('library_genres')) {
            return;
        }

        $genreMap = [];
        $genres = DB::table('library_genres')->get();
        foreach ($genres as $genre) {
            $newId = DB::table('content_categories')->insertGetId([
                'bot_id' => $genre->bot_id,
                'title' => $genre->name,
                'page' => $genre->page,
                'sort_order' => $genre->sort_order,
                'is_active' => $genre->is_active,
                'created_at' => $genre->created_at,
                'updated_at' => $genre->updated_at,
            ]);
            $genreMap[$genre->id] = $newId;
        }

        if (!Schema::hasTable('library_books')) {
            return;
        }

        $bookMap = [];
        $orderPerCategory = [];
        $books = DB::table('library_books')->where('is_active', true)->orderBy('id')->get();
        foreach ($books as $book) {
            $pivot = DB::table('library_book_genre')->where('book_id', $book->id)->first();
            if (!$pivot || !isset($genreMap[$pivot->genre_id])) {
                continue;
            }
            $categoryId = $genreMap[$pivot->genre_id];
            $orderPerCategory[$categoryId] = ($orderPerCategory[$categoryId] ?? 0) + 1;

            $itemId = DB::table('content_items')->insertGetId([
                'bot_id' => $book->bot_id,
                'category_id' => $categoryId,
                'title' => $book->title,
                'queue_order' => $orderPerCategory[$categoryId],
                'is_active' => $book->is_active,
                'created_at' => $book->created_at,
                'updated_at' => $book->updated_at,
            ]);
            $bookMap[$book->id] = $itemId;

            $media = DB::table('library_book_media')->where('book_id', $book->id)->get();
            foreach ($media as $m) {
                DB::table('content_assets')->insert([
                    'content_item_id' => $itemId,
                    'type' => $m->type,
                    'content_url' => $m->content_url,
                    'telegram_file_id' => $m->telegram_file_id,
                    'bale_file_id' => $m->bale_file_id,
                    'created_at' => $m->created_at,
                    'updated_at' => $m->updated_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_broadcast_jobs');
        Schema::dropIfExists('content_user_progress');
        Schema::dropIfExists('content_pending_uploads');
        Schema::dropIfExists('content_assets');
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('content_categories');
    }
};
