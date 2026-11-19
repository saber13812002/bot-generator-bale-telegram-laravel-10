<?php

namespace App\Http\Requests\Api\Quran;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'language' => 'required|string|max:10',
            'translator' => 'required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'language.required' => trans('bot.language is required'),
            'translator.required' => trans('bot.translator is required'),
        ];
    }
}
