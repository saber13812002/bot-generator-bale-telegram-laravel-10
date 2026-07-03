@extends('layouts.web')

@section('title', trans('bot-owner.create_title', ['name' => $endpoint->name ?? '']))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
<style>
.wizard-step { transition: all 0.3s ease; }
.wizard-step.active { display: block; }
.wizard-step:not(.active) { display: none; }
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
