<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRssFeedWebOriginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'origin' => 'required|string',
            'media_id' => 'required|string',
            'media_url' => 'nullable|string',
            'image' => 'required|url',
            'link' => 'required|url',
            'title' => 'required|string',
            'description' => 'required|string',
        ];
    }
}
