<?php

namespace App\Http\Requests\Api\Quran;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mp3_enable' => 'sometimes|boolean',
            'mp3_reciter' => 'sometimes|string|max:50',
            'quran_translation_language' => 'sometimes|string|max:10',
            'quran_translation_translator' => 'sometimes|string|max:100',
            'quran_transliteration_tr' => 'sometimes|boolean',
            'quran_transliteration_en' => 'sometimes|boolean',
            'placequran_enable' => 'sometimes|boolean',
        ];
    }
}
