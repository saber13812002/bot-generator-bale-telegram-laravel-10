<?php

namespace Database\Seeders;

use App\Models\WebhookEndpoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class BotCreationWizardStepsSeeder extends Seeder
{
    /**
     * Define wizard_steps for endpoints that require extra configuration.
     */
    public function run(): void
    {
        // Presenter Bot - requires content items
        $this->updateWizardSteps('presenter-bot', [
            [
                'id' => 'presenter_content',
                'type' => 'collection',
                'label_fa' => 'محتوای ربات پرزنتر',
                'label_en' => 'Presenter Bot Content',
                'required' => false,
                'order' => 4,
                'accepts' => ['text', 'photo', 'video', 'voice', 'audio', 'document'],
                'help_fa' => 'محتوایی که ربات به ترتیب نمایش می‌دهد',
                'help_en' => 'Content items the bot will display in order',
            ],
        ]);

        // Rating Bot - requires rating items
        $this->updateWizardSteps('rating-bot', [
            [
                'id' => 'rating_content',
                'type' => 'collection',
                'label_fa' => 'عبارت‌های نظر سنجی',
                'label_en' => 'Rating Items',
                'required' => false,
                'order' => 4,
                'accepts' => ['text', 'photo', 'video'],
                'help_fa' => 'عبارت‌هایی که کاربران به آنها امتیاز می‌دهند',
                'help_en' => 'Items users will rate',
            ],
        ]);

        // Psychology Test Bot - requires questions and categories
        $this->updateWizardSteps('psychology-test', [
            [
                'id' => 'psychology_questions',
                'type' => 'complex_wizard',
                'label_fa' => 'سوالات تست روانشناسی',
                'label_en' => 'Psychology Test Questions',
                'required' => false,
                'order' => 4,
                'wizard_type' => 'multi_step',
                'format_hint_fa' => 'سوال [دسته, وزن, جهت]',
                'format_hint_en' => 'Question [Category, Weight, Direction]',
                'wizard' => [
                    'steps' => [
                        [
                            'id' => 'questions',
                            'type' => 'bulk_text',
                            'label_fa' => 'سوالات را با فرمت وارد کنید',
                            'label_en' => 'Enter questions with format',
                            'format_hint_fa' => 'سوال [دسته, وزن, جهت]  مثال: آیا در جمع‌ها راحت هستید؟ [برون‌گرا, 1.0, 1]',
                            'format_hint_en' => 'Question [Category, Weight, Direction]',
                        ],
                        [
                            'id' => 'category_descriptions',
                            'type' => 'repeated_text',
                            'label_fa' => 'توضیحات دسته‌ها',
                            'label_en' => 'Category descriptions',
                        ],
                    ],
                ],
            ],
        ]);

        // Content Submission Bot - requires channel/group configuration
        $this->updateWizardSteps('content-submission', [
            [
                'id' => 'content_channel_confirm',
                'type' => 'confirm',
                'label_fa' => 'آیا در کانال ادمین عضو هستید؟',
                'label_en' => 'Are you a member of the admin channel?',
                'required' => true,
                'order' => 4,
            ],
            [
                'id' => 'content_channel_id',
                'type' => 'forward',
                'label_fa' => 'شناسه کانال انتشار',
                'label_en' => 'Channel ID',
                'required' => true,
                'order' => 5,
                'help_fa' => 'یک پیام از کانال فوروارد کنید یا شناسه کانال را وارد کنید',
                'help_en' => 'Forward a message from the channel or enter the channel ID',
            ],
            [
                'id' => 'content_need_approval',
                'type' => 'yes_no',
                'label_fa' => 'آیا نیاز به تایید گروه دارید؟',
                'label_en' => 'Need approval group?',
                'required' => true,
                'order' => 6,
            ],
            [
                'id' => 'content_group_id',
                'type' => 'forward',
                'label_fa' => 'شناسه گروه تایید',
                'label_en' => 'Approval Group ID',
                'required' => false,
                'optional' => true,
                'order' => 7,
                'help_fa' => 'یک پیام از گروه تایید فوروارد کنید (اختیاری)',
                'help_en' => 'Forward a message from the approval group (optional)',
                'condition' => [
                    'field' => 'content_need_approval',
                    'operator' => '=',
                    'value' => 'yes',
                ],
            ],
            [
                'id' => 'content_required_approvals',
                'type' => 'number',
                'label_fa' => 'تعداد تاییدکنندگان مورد نیاز',
                'label_en' => 'Required Approvals',
                'required' => false,
                'optional' => true,
                'order' => 8,
                'help_fa' => '۱ یا ۲ نفر',
                'help_en' => '1 or 2',
                'condition' => [
                    'field' => 'content_need_approval',
                    'operator' => '=',
                    'value' => 'yes',
                ],
            ],
        ]);

        // Book Library - requires reader token (optional)
        $this->updateWizardSteps('book-library', [
            [
                'id' => 'reader_token',
                'type' => 'text',
                'label_fa' => 'توکن ربات کتابخوان (اختیاری)',
                'label_en' => 'Reader Bot Token (optional)',
                'required' => false,
                'order' => 4,
                'help_fa' => 'اگر ربات کتابخوان جداگانه دارید، توکن آن را وارد کنید',
                'help_en' => 'If you have a separate reader bot, enter its token',
            ],
        ]);

        // Weather Bot - only Persian and English for language
        $this->updateWizardSteps('weather-bot', [
            [
                'id' => 'language',
                'type' => 'select',
                'label_fa' => 'زبان ربات',
                'label_en' => 'Bot Language',
                'required' => true,
                'order' => 2,
                'options' => [
                    ['value' => 'fa', 'label_fa' => 'فارسی', 'label_en' => 'Persian'],
                    ['value' => 'en', 'label_fa' => 'انگلیسی', 'label_en' => 'English'],
                ],
            ],
        ]);

        Log::info('✅ Bot Creation Wizard Steps seeded successfully');
    }

    /**
     * Update wizard_steps for an endpoint.
     */
    private function updateWizardSteps(string $endpointId, array $extraSteps): void
    {
        $endpoint = WebhookEndpoint::where('endpoint_id', $endpointId)->first();

        if (!$endpoint) {
            Log::warning("Endpoint not found: {$endpointId}");
            return;
        }

        $endpoint->wizard_steps = $extraSteps;
        $endpoint->save();

        $this->command->info("✅ Wizard steps updated for: {$endpointId}");
    }
}
