<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BotRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'origin' => [
                'required',
                Rule::in(['bale', 'telegram', 'soroosh']),
            ],
            'token' => 'required',
            'language' => 'in:fa,en,ar-IQ,zh-CN,fr,de-DE,fa,ru,es,tr,ur,he',
            'bot_mother_id' => 'required',
            'command_type' => 'string'
        ];
    }
}
