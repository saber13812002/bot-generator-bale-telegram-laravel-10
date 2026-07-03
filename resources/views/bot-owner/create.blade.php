@extends('layouts.web')

@section('title', trans('bot-owner.create_title', ['name' => $endpoint->name ?? '']))

@push('styles')
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Tahoma, 'Segoe UI', system-ui, sans-serif; background: #f9fafb; color: #1f2937; line-height: 1.6; }
.min-h-screen { min-height: 100vh; }
.bg-gray-50 { background: #f9fafb; }
.bg-white { background: #fff; }
.shadow-sm { box-shadow: 0 1px 2px rgba(0,0,0,.05); }
.shadow { box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.rounded { border-radius: 8px; }
.rounded-lg { border-radius: 12px; }
.max-w-3xl { max-width: 768px; margin: 0 auto; }
.mx-auto { margin-left: auto; margin-right: auto; }
.px-4 { padding-left: 16px; padding-right: 16px; }
.py-4 { padding-top: 16px; padding-bottom: 16px; }
.py-8 { padding-top: 32px; padding-bottom: 32px; }
.p-4 { padding: 16px; }
.p-6 { padding: 24px; }
.p-3 { padding: 12px; }
.mb-1 { margin-bottom: 4px; }
.mb-2 { margin-bottom: 8px; }
.mb-4 { margin-bottom: 16px; }
.mb-6 { margin-bottom: 24px; }
.mt-1 { margin-top: 4px; }
.mt-6 { margin-top: 24px; }
.text-2xl { font-size: 24px; font-weight: 700; }
.text-lg { font-size: 18px; font-weight: 600; }
.text-sm { font-size: 14px; }
.text-xs { font-size: 12px; }
.font-bold { font-weight: 700; }
.font-medium { font-weight: 500; }
.font-mono { font-family: monospace; }
.text-gray-400 { color: #9ca3af; }
.text-gray-500 { color: #6b7280; }
.text-gray-600 { color: #4b5563; }
.text-gray-700 { color: #374151; }
.text-red-600 { color: #dc2626; }
.text-red-700 { color: #b91c1c; }
.text-blue-600 { color: #2563eb; }
.text-green-600 { color: #16a34a; }
.hover\:text-red-600:hover { color: #dc2626; }
.hover\:underline:hover { text-decoration: underline; }
.hover\:bg-red-700:hover { background: #b91c1c; }
.hover\:bg-gray-100:hover { background: #f3f4f6; }
.hover\:bg-gray-300:hover { background: #d1d5db; }
.border { border: 1px solid #e5e7eb; }
.border-red-200 { border-color: #fecaca; }
.border-blue-200 { border-color: #bfdbfe; }
.border-yellow-200 { border-color: #fef08a; }
.bg-red-50 { background: #fef2f2; }
.bg-blue-50 { background: #eff6ff; }
.bg-red-600 { background: #dc2626; }
.bg-green-600 { background: #16a34a; }
.bg-blue-600 { background: #2563eb; }
.bg-gray-200 { background: #e5e7eb; }
.text-white { color: #fff; }
.px-3 { padding-left: 12px; padding-right: 12px; }
.px-4 { padding-left: 16px; padding-right: 16px; }
.px-5 { padding-left: 20px; padding-right: 20px; }
.px-6 { padding-left: 24px; padding-right: 24px; }
.py-2 { padding-top: 8px; padding-bottom: 8px; }
.py-3 { padding-top: 12px; padding-bottom: 12px; }
.w-full { width: 100%; }
.flex { display: flex; }
.gap-2 { gap: 8px; }
.gap-4 { gap: 16px; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.inline-flex { display: inline-flex; }
.form-radio { margin-left: 4px; }
.whitespace-pre-line { white-space: pre-line; }
.list-disc { list-style: disc; }
.list-inside { list-style-position: inside; }
.space-y-2 > * + * { margin-top: 8px; }
.space-y-4 > * + * { margin-top: 16px; }
.transition { transition: all .2s; }
.wizard-step { transition: all 0.3s ease; display: none; }
.wizard-step.active { display: block; }
#progress-bar { transition: width 0.3s ease; }
select, input, textarea { border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 12px; font-size: 14px; width: 100%; }
select:focus, input:focus, textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
button { cursor: pointer; border: none; font-size: 14px; }
</style>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
    <header class="bg-white shadow-sm">
        <div class="max-w-3xl mx-auto px-4 py-4">
            <a href="{{ route('bot-owner.dashboard') }}" class="text-gray-600 hover:text-red-600 text-sm">
                ← {{ trans('bot-owner.back_to_dashboard') }}
            </a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-2">{{ trans('bot-owner.create_title', ['name' => $endpoint->name ?? '']) }}</h1>
        <p class="text-gray-600 mb-6">{{ $endpoint->description ?? '' }}</p>

        @if($endpoint->usage_instructions ?? false)
            <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-6 text-sm whitespace-pre-line">
                {{ $endpoint->usage_instructions }}
            </div>
        @endif

        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600 mb-4">{{ trans('bot-owner.botfather_hint') }}</p>

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded p-3 mb-4 text-sm text-red-700">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 rounded p-3 mb-4 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('bot-owner.create.store', $endpoint->endpoint_id ?? '') }}" id="wizard-form">
                @csrf

                <div id="wizard-progress" class="mb-6">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>{{ trans('bot-owner.steps_progress') }}</span>
                        <span id="step-counter">1 / {{ $fields->count() }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div id="progress-bar" class="bg-red-600 h-2 rounded-full transition-all" style="width: {{ $fields->count() > 0 ? (1 / $fields->count() * 100) : 0 }}%"></div>
                    </div>
                </div>

                <div id="wizard-steps">
                    @foreach($fields as $index => $field)
                        <div class="wizard-step @if($index === 0) active @endif"
                             data-step="{{ $index }}"
                             data-field-id="{{ $field->id }}">
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-1">
                                    {{ $field->label }}
                                    @if(!$field->required)
                                        <span class="text-gray-400 text-xs">({{ trans('bot-owner.optional') }})</span>
                                    @endif
                                </label>

                                @if($field->help)
                                    <p class="text-xs text-gray-500 mb-2">{{ $field->help }}</p>
                                @endif

                                @if($field->formatHint)
                                    <p class="text-xs text-blue-600 mb-2">📋 {{ $field->formatHint }}</p>
                                @endif

                                @switch($field->type)
                                    @case('select')
                                        <select name="{{ $field->id }}" class="w-full border rounded px-3 py-2" {{ $field->required ? 'required' : '' }}>
                                            @foreach($field->options ?? [] as $option)
                                                <option value="{{ $option['value'] }}">
                                                    {{ $option['label_fa'] ?? $option['label_en'] ?? $option['label'] ?? $option['value'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @break

                                    @case('text')
                                        <input type="text" name="{{ $field->id }}"
                                               class="w-full border rounded px-3 py-2 font-mono text-sm"
                                               placeholder="{{ $field->placeholder ?? '' }}"
                                               {{ $field->required ? 'required' : '' }}>
                                        @break

                                    @case('number')
                                        <input type="number" name="{{ $field->id }}"
                                               class="w-full border rounded px-3 py-2"
                                               {{ $field->required ? 'required' : '' }}>
                                        @break

                                    @case('yes_no')
                                        <div class="flex gap-4">
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="{{ $field->id }}" value="yes" class="form-radio">
                                                <span class="mr-2">بله</span>
                                            </label>
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="{{ $field->id }}" value="no" class="form-radio" checked>
                                                <span class="mr-2">خیر</span>
                                            </label>
                                        </div>
                                        @break

                                    @case('confirm')
                                        <div class="flex gap-4">
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="{{ $field->id }}" value="yes" class="form-radio" checked>
                                                <span class="mr-2">بله</span>
                                            </label>
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="{{ $field->id }}" value="no" class="form-radio">
                                                <span class="mr-2">خیر</span>
                                            </label>
                                        </div>
                                        @break

                                    @case('forward')
                                        <input type="text" name="{{ $field->id }}"
                                               class="w-full border rounded px-3 py-2 text-sm"
                                               placeholder="شناسه کانال/گروه را وارد کنید">
                                        @if($field->optional)
                                            <p class="text-xs text-gray-400 mt-1">{{ trans('bot-owner.skip_hint') }}</p>
                                        @endif
                                        @break

                                    @case('multi_line_text')
                                    @case('bulk_text')
                                        <textarea name="{{ $field->id }}" rows="5"
                                                  class="w-full border rounded px-3 py-2 text-sm font-mono"></textarea>
                                        <p class="text-xs text-gray-400 mt-1">{{ trans('bot-owner.each_line_separate') }}</p>
                                        @break

                                    @case('collection')
                                        <div class="space-y-2">
                                            <div class="flex gap-2">
                                                <input type="text" id="{{ $field->id }}-input"
                                                       class="flex-1 border rounded px-3 py-2 text-sm"
                                                       placeholder="متن را وارد کنید">
                                                <button type="button" onclick="addCollectionItem('{{ $field->id }}')"
                                                        class="px-3 py-2 bg-gray-200 rounded hover:bg-gray-300 text-sm">
                                                    +
                                                </button>
                                            </div>
                                            <ul id="{{ $field->id }}-list" class="list-disc list-inside text-sm text-gray-700"></ul>
                                        </div>
                                        <input type="hidden" name="{{ $field->id }}" id="{{ $field->id }}-hidden">
                                        @break

                                    @default
                                        <input type="text" name="{{ $field->id }}"
                                               class="w-full border rounded px-3 py-2"
                                               {{ $field->required ? 'required' : '' }}>
                                @endswitch
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-between mt-6">
                    <button type="button" id="wizard-prev" onclick="wizardPrev()"
                            class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300" style="display:none">
                        ← {{ trans('bot-owner.prev_step') }}
                    </button>
                    <button type="button" id="wizard-next" onclick="wizardNext()"
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        {{ trans('bot-owner.next_step') }} →
                    </button>
                    <button type="submit" id="wizard-submit"
                            class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700" style="display:none">
                        ✅ {{ trans('bot-owner.submit_create') }}
                    </button>
                </div>
            </form>

            <script>
            const wizardSteps = document.querySelectorAll('.wizard-step');
            let currentWizardStep = 0;

            function wizardNext() {
                if (currentWizardStep < wizardSteps.length - 1) {
                    wizardSteps[currentWizardStep].classList.remove('active');
                    currentWizardStep++;
                    wizardSteps[currentWizardStep].classList.add('active');
                }
                updateWizardUI();
            }

            function wizardPrev() {
                if (currentWizardStep > 0) {
                    wizardSteps[currentWizardStep].classList.remove('active');
                    currentWizardStep--;
                    wizardSteps[currentWizardStep].classList.add('active');
                }
                updateWizardUI();
            }

            function updateWizardUI() {
                const total = wizardSteps.length;
                document.getElementById('wizard-prev').style.display = currentWizardStep > 0 ? 'block' : 'none';
                document.getElementById('wizard-next').style.display = currentWizardStep < total - 1 ? 'block' : 'none';
                document.getElementById('wizard-submit').style.display = currentWizardStep === total - 1 ? 'block' : 'none';
                document.getElementById('step-counter').textContent = (currentWizardStep + 1) + ' / ' + total;
                document.getElementById('progress-bar').style.width = ((currentWizardStep + 1) / total * 100) + '%';
            }

            function addCollectionItem(fieldId) {
                const input = document.getElementById(fieldId + '-input');
                const list = document.getElementById(fieldId + '-list');
                const hidden = document.getElementById(fieldId + '-hidden');
                if (!input.value.trim()) return;
                const li = document.createElement('li');
                li.textContent = input.value;
                li.className = 'text-gray-700 py-1';
                list.appendChild(li);
                const items = JSON.parse(hidden.value || '[]');
                items.push(input.value);
                hidden.value = JSON.stringify(items);
                input.value = '';
            }
            </script>
        </div>
    </main>
</div>
@endsection
