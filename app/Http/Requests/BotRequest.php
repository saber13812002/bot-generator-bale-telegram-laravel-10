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
                // Phase 4: soroush و rubika به عنوان origin مجاز اضافه شدند.
                // «soroosh» (قدیمی/مغایر) تا فاز ۶ نگه داشته شده تا رفتار
                // موجود خراب نشود؛ نرمال‌سازی آن در فاز ۶ انجام می‌شود.
                Rule::in(['bale', 'telegram', 'soroosh', 'gap', 'soroush', 'rubika']),
            ],
            'token' => 'required',
            'language' => 'in:fa,en,ar-IQ,zh-CN,fr,de-DE,ru,es,tr,ur,he,az,bs,it,id,sw,pt-BR,pt-PT',
            'bot_mother_id' => 'nullable|integer',
            'command_type' => 'string'
        ];
    }
}
//'target_locales' => ['ar-IQ', 'az', 'bs', 'zh-CN', 'fr', 'de-DE', 'he', 'fa', 'pt-BR', 'pt-PT', 'ru', 'es', 'tr', 'ur']
