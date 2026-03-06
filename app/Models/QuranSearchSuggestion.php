<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuranSearchSuggestion extends Model
{
    use HasFactory;

    const SOURCE_SINGLE_RESULT = 'single_result';
    const SOURCE_CLICKED = 'clicked';

    protected $fillable = [
        'search_phrase',
        'result_count',
        'sura',
        'aya',
        'chat_id',
        'type',
        'source',
    ];

    protected $casts = [
        'result_count' => 'integer',
        'sura' => 'integer',
        'aya' => 'integer',
    ];
}
