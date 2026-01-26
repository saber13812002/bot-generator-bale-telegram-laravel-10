<?php

namespace App\Http\Resources\Api\Quran;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'mp3_enable' => $this->mp3_enable ?? false,
            'mp3_reciter' => $this->mp3_reciter ?? null,
            'quran_translation_language' => $this->quran_translation_language ?? null,
            'quran_translation_translator' => $this->quran_translation_translator ?? null,
            'quran_transliteration_tr' => $this->quran_transliteration_tr ?? false,
            'quran_transliteration_en' => $this->quran_transliteration_en ?? false,
            'placequran_enable' => $this->placequran_enable ?? false,
        ];
    }
}
