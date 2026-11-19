<?php

namespace App\Http\Resources\Api\Quran;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AyahResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? null,
            'sura' => $this->sura ?? null,
            'aya' => $this->aya ?? null,
            'arabic_text' => $this->text ?? $this->arabic_text ?? null,
            'simple_text' => $this->simple ?? null,
            'translation' => $this->translation ?? null,
            'translation_language' => $this->translation_language ?? null,
            'translation_translator' => $this->translation_translator ?? null,
            'transliteration_tr' => $this->transliteration_tr ?? null,
            'transliteration_en' => $this->transliteration_en ?? null,
            'page' => $this->page ?? null,
            'juz' => $this->juz ?? null,
            'hezb' => $this->hezb ?? null,
            'sura_name' => $this->sura_name ?? null,
            'sura_name_arabic' => $this->sura_name_arabic ?? null,
            'next_ayah' => $this->next_ayah ?? null,
            'previous_ayah' => $this->previous_ayah ?? null,
        ];
    }
}
