<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ideas', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint_id')->nullable()->comment('رباط مرتبط');
            $table->string('title', 255)->comment('عنوان ایده');
            $table->text('description')->comment('توضیحات ایده');
            $table->string('submitter_name', 100)->nullable()->comment('نام ارسال‌کننده');
            $table->string('submitter_contact', 200)->nullable()->comment('راه ارتباطی');
            $table->string('status')->default('pending')->comment('pending|reviewing|approved|rejected|done');
            $table->text('admin_note')->nullable()->comment('یادداشت ادمین');
            $table->unsignedBigInteger('reviewed_by')->nullable()->comment('بررسی‌کننده');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ideas');
    }
};
