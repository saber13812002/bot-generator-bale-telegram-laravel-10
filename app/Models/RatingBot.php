<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RatingBot extends Model
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

    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }

    public function getLines(): array
    {
        $lines = explode("\n", $this->content);
        $lines = array_filter($lines, static fn ($line) => trim($line) !== '');
        return array_values($lines);
    }

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

