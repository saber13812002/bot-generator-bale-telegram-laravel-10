<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تاریخچه عملیات ادمین‌ها (audit log) برای هر محتوا.
 */
class ContentSyncOperation extends Model
{
    public const OP_REGISTER = 'register';
    public const OP_ATTACH_TEXT = 'attach_text';
    public const OP_PUBLISH = 'publish';
    public const OP_ENQUEUE = 'enqueue';
    public const OP_RETRY = 'retry';
    public const OP_REPORT = 'report';
    public const OP_CSV_EXPORT = 'csv_export';
    public const OP_ADD_ADMIN = 'add_admin';
    public const OP_UNLINK = 'unlink';

    public const RESULT_OK = 'ok';
    public const RESULT_FAILED = 'failed';
    public const RESULT_SKIPPED = 'skipped';
    public const RESULT_DUPLICATE = 'duplicate';

    protected $table = 'content_sync_operations';

    protected $fillable = [
        'bot_id',
        'admin_chat_id',
        'admin_origin',
        'content_id',
        'operation',
        'result',
        'error',
        'attempted_at',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
    ];

    public function content()
    {
        return $this->belongsTo(ContentSyncContent::class, 'content_id');
    }

    public static function log(
        int $botId,
        ?string $adminChatId,
        string $origin,
        ?int $contentId,
        string $operation,
        string $result,
        ?string $error = null
    ): self {
        return static::create([
            'bot_id' => $botId,
            'admin_chat_id' => $adminChatId,
            'admin_origin' => $origin,
            'content_id' => $contentId,
            'operation' => $operation,
            'result' => $result,
            'error' => $error,
            'attempted_at' => now(),
        ]);
    }
}
