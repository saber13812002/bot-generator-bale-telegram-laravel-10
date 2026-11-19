<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PresenterBot extends Model
{
    use HasFactory;

    protected $casts = [
        'items' => 'array',
    ];

    protected $fillable = [
        'bot_id',
        'content',
        'items',
    ];

    /**
     * Relationship با Bot
     */
    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * دریافت خطوط محتوا (با حذف خطوط خالی)
     * 
     * @return array
     */
    public function getLines(): array
    {
        $lines = explode("\n", $this->content);
        // حذف خطوط خالی
        $lines = array_filter($lines, function($line) {
            return trim($line) !== '';
        });
        // بازگرداندن به صورت array با index های متوالی
        return array_values($lines);
    }

    /**
     * دریافت تعداد خطوط
     * 
     * @return int
     */
    public function getTotalLines(): int
    {
        return count($this->getLines());
    }

    public function getItems(): array
    {
        $items = $this->items;
        if (is_array($items) && count($items) > 0) {
            return array_values(array_filter($items, static function ($item) {
                return is_array($item) && isset($item['type']);
            }));
        }

        return array_map(static fn ($line) => ['type' => 'text', 'content' => $line], $this->getLines());
    }
}
