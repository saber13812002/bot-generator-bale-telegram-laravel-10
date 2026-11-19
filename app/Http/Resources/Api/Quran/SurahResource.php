<?php

namespace App\Http\Resources\Api\Quran;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SurahResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'arabic' => $this->arabic ?? null,
            'name' => $this->name ?? null,
            'ayah_count' => $this->ayah ?? null,
            'meccamedinan' => $this->meccamedinan ?? null,
            'sortnozol' => $this->sortnozol ?? null,
        ];
    }
}
