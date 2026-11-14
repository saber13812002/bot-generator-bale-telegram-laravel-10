<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * WS-I — RAG & Embedding readiness: one stored embedding per source.
 */
class AiEmbedding extends Model
{
    protected $table = 'ai_embeddings';

    protected $fillable = [
        'source_type',
        'source_id',
        'bot_id',
        'content',
        'model',
        'dimensions',
        'vector',
        'status',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'bot_id' => 'integer',
        'dimensions' => 'integer',
        'vector' => 'array',
    ];
}
