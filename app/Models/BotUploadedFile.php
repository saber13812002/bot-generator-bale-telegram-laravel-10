<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotUploadedFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'bot_type',
        'file_unique_key',
        'file_type',
        'file_id',
        'file_unique_id',
        'file_size',
        'width',
        'height',
        'metadata',
        'upload_response',
    ];

    protected $casts = [
        'metadata' => 'array',
        'upload_response' => 'array',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    /**
     * رابطه با Bot
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * پیدا کردن فایل بر اساس bot_id, bot_type و file_unique_key
     */
    public static function findByUniqueKey(int $botId, string $botType, string $fileUniqueKey): ?self
    {
        return self::where('bot_id', $botId)
            ->where('bot_type', $botType)
            ->where('file_unique_key', $fileUniqueKey)
            ->first();
    }

    /**
     * پیدا کردن file_id بر اساس bot_id, bot_type و file_unique_key
     */
    public static function getFileId(int $botId, string $botType, string $fileUniqueKey): ?string
    {
        $file = self::findByUniqueKey($botId, $botType, $fileUniqueKey);
        return $file ? $file->file_id : null;
    }
}
