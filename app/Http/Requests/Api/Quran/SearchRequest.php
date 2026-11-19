<?php

namespace App\Http\Requests\Api\Quran;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => 'required|string|min:1|max:500',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'query.required' => trans('bot.search query is required'),
            'query.min' => trans('bot.search query must be at least 1 character'),
        ];
    }
}
