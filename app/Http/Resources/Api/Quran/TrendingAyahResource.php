<?php

namespace App\Http\Resources\Api\Quran;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrendingAyahResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sura' => $this->sura,
            'aya' => $this->aya,
            'view_count' => $this->view_count ?? $this->count ?? 0,
            'rank' => $this->rank ?? null,
            'arabic_text' => $this->arabic_text ?? null,
            'translation' => $this->translation ?? null,
        ];
    }
}
