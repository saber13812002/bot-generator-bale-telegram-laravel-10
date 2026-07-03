<?php

namespace App\Modules\BotCreation\Services;

use App\Modules\BotCreation\Models\FieldDefinition;

/**
 * Renders bot creation wizard steps as HTML for the Bot Owner web panel.
 */
class WebRenderer
{
    public function __construct(
        private readonly FieldRegistry $fieldRegistry,
    ) {}

    /**
     * Render all steps as a multi-step wizard form.
     *
     * @param FieldDefinition[] $fields
     */
    public function renderWizardForm(array $fields, string $endpointId, string $actionUrl): string
    {
        $stepsHtml = '';
        foreach ($fields as $index => $field) {
            $handler = $this->fieldRegistry->get($field->type);
            $html = $handler->renderWebHtml($field, []);

            $hidden = $index > 0 ? 'style="display:none"' : '';
            $stepsHtml .= <<<HTML
            <div class="wizard-step" data-step="{$index}" {$hidden}>
                {$html}
            </div>
            HTML;
        }

        return <<<HTML
        <form method="POST" action="{$actionUrl}" id="wizard-form" class="space-y-4">
            <input type="hidden" name="_token" value="csrf_token">
            <input type="hidden" name="endpoint_id" value="{$endpointId}">
            
            <div id="wizard-steps">
                {$stepsHtml}
            </div>
            
            <div class="flex justify-between mt-6">
                <button type="button" id="wizard-prev" onclick="wizardPrev()" 
                        class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300" style="display:none">
                    ← قبلی
                </button>
                <button type="button" id="wizard-next" onclick="wizardNext()"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    بعدی →
                </button>
                <button type="submit" id="wizard-submit" 
                        class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700" style="display:none">
                    ✅ ساخت ربات
                </button>
            </div>
        </form>
        
        <script>
        const wizardSteps = document.querySelectorAll('.wizard-step');
        let currentWizardStep = 0;
        
        function wizardNext() {
            if (currentWizardStep < wizardSteps.length - 1) {
                wizardSteps[currentWizardStep].style.display = 'none';
                currentWizardStep++;
                wizardSteps[currentWizardStep].style.display = 'block';
            }
            updateWizardButtons();
        }
        
        function wizardPrev() {
            if (currentWizardStep > 0) {
                wizardSteps[currentWizardStep].style.display = 'none';
                currentWizardStep--;
                wizardSteps[currentWizardStep].style.display = 'block';
            }
            updateWizardButtons();
        }
        
        function updateWizardButtons() {
            document.getElementById('wizard-prev').style.display = currentWizardStep > 0 ? 'block' : 'none';
            document.getElementById('wizard-next').style.display = currentWizardStep < wizardSteps.length - 1 ? 'block' : 'none';
            document.getElementById('wizard-submit').style.display = currentWizardStep === wizardSteps.length - 1 ? 'block' : 'none';
        }
        </script>
        HTML;
    }

    /**
     * Render fields as a simple single-page form (for simple endpoints).
     *
     * @param FieldDefinition[] $fields
     */
    public function renderSimpleForm(array $fields, string $endpointId, string $actionUrl): string
    {
        $fieldsHtml = '';
        foreach ($fields as $field) {
            $handler = $this->fieldRegistry->get($field->type);
            $fieldsHtml .= $handler->renderWebHtml($field, []);
        }

        return <<<HTML
        <form method="POST" action="{$actionUrl}" class="space-y-4">
            <input type="hidden" name="endpoint_id" value="{$endpointId}">
            {$fieldsHtml}
            <button type="submit" 
                    class="w-full px-6 py-3 bg-red-600 text-white rounded hover:bg-red-700">
                ✅ ساخت ربات
            </button>
        </form>
        HTML;
    }
}
