<?php

namespace App\Http\Requests\Api\Quran;

use Illuminate\Foundation\Http\FormRequest;

class GetAyahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'language' => 'sometimes|string|max:10',
            'translator' => 'sometimes|string|max:100',
        ];
    }
}
