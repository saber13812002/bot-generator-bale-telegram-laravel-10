<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListBotConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'menu_json',
        'raw_content',
    ];

    protected $casts = [
        'menu_json' => 'array',
    ];

    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Get menu tree; empty array if not set.
     *
     * @return array{title: string, children?: array}
     */
    public function getMenuTree(): array
    {
        $tree = $this->menu_json;
        if (!is_array($tree) || empty($tree)) {
            return ['title' => 'فهرست', 'children' => []];
        }
        if (empty($tree['children'])) {
            $tree['children'] = [];
        }
        return $tree;
    }

    public function hasMenu(): bool
    {
        $tree = $this->menu_json;
        return is_array($tree) && !empty($tree['children']);
    }
}
