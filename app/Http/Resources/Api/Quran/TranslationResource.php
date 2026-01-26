<?php

namespace App\Http\Resources\Api\Quran;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranslationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'language' => $this->language,
            'translator_name' => $this->translator_name,
            'translate_full_name' => $this->translate_full_name ?? null,
            'is_complete' => $this->is_complete ?? null,
            'completeness_percentage' => $this->completeness_percentage ?? null,
        ];
    }
}
