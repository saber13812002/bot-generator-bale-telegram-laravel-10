<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add tracking_code to ideas
        Schema::table('ideas', function (Blueprint $table) {
            if (!Schema::hasColumn('ideas', 'tracking_code')) {
                $table->string('tracking_code', 20)->unique()->nullable()->after('id');
            }
            if (!Schema::hasColumn('ideas', 'phone')) {
                $table->string('phone', 20)->nullable()->after('submitter_contact');
            }
            if (!Schema::hasColumn('ideas', 'email')) {
                $table->string('email', 200)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('ideas', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
            if (!Schema::hasColumn('ideas', 'notify_by_bot')) {
                $table->boolean('notify_by_bot')->default(false)->after('email_verified_at');
            }
        });

        // Messages table (ticket replies)
        Schema::create('idea_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idea_id');
            $table->string('sender_type')->default('user')->comment('user|admin|system');
            $table->string('sender_name', 100)->nullable();
            $table->text('message');
            $table->string('attachment_type')->nullable();
            $table->string('attachment_url')->nullable();
            $table->timestamps();

            $table->foreign('idea_id')->references('id')->on('ideas')->onDelete('cascade');
            $table->index('idea_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idea_messages');

        Schema::table('ideas', function (Blueprint $table) {
            $columns = ['tracking_code', 'phone', 'email', 'email_verified_at', 'notify_by_bot'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('ideas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
