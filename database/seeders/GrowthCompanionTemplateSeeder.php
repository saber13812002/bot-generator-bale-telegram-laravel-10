<?php

namespace Database\Seeders;

use App\Models\GrowthTemplate;
use Illuminate\Database\Seeder;

class GrowthCompanionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $data) {
            $questions = $data['questions'];
            unset($data['questions']);

            $template = GrowthTemplate::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );

            foreach ($questions as $question) {
                $template->questions()->updateOrCreate(
                    ['question_key' => $question['question_key']],
                    $question
                );
            }
        }
    }

    private function templates(): array
    {
        return [
            $this->template('health', 'سلامت', 'Health', 'daily_health_reflection', 'daily_health_checkin', [
                ['fa' => 'امروز برای بدنت چه کاری کردی؟', 'en' => 'What did you do for your body today?'],
                ['fa' => 'امروز انرژی‌ات چطور بود؟', 'en' => 'How was your energy today?'],
                ['fa' => 'یک کار کوچک برای مراقبت از خودت چه می‌توانست باشد؟', 'en' => 'What is one small way you could have taken care of yourself today?'],
                ['fa' => 'امروز خواب یا حرکت را چطور تجربه کردی؟', 'en' => 'How did sleep or movement feel today?'],
            ]),
            $this->template('family', 'خانواده', 'Family', 'daily_family_reflection', 'daily_family_presence', [
                ['fa' => 'امروز با خانواده‌ات چه لحظه‌ای داشتی؟', 'en' => 'What moment did you have with your family today?'],
                ['fa' => 'اگر بخواهی یک کار کوچک برای خانواده‌ات بهتر انجام دهی، چیست؟', 'en' => 'What is one small thing you could do better for your family?'],
                ['fa' => 'امروز چقدر حضور داشتی؟', 'en' => 'How present were you with your family today?'],
                ['fa' => 'کسی در خانواده امروز به توجه تو نیاز داشت؟', 'en' => 'Did someone in your family need your attention today?'],
            ]),
            $this->template('work', 'کار', 'Work', 'daily_work_reflection', 'daily_work_focus', [
                ['fa' => 'امروز در کار روی چه چیزی بیشتر تمرکز داشتی؟', 'en' => 'What did you focus on at work today?'],
                ['fa' => 'کجا احساس کردی وقتت خوب استفاده شد؟', 'en' => 'Where did your time feel well used today?'],
                ['fa' => 'اگر فقط یک چیز را در کار امروز تغییر دهی، چیست؟', 'en' => 'If you changed one thing about work today, what would it be?'],
                ['fa' => 'امروز چه چیزی در کارت جلو رفت؟', 'en' => 'What moved forward in your work today?'],
            ]),
            $this->template('spirituality', 'معنویت', 'Spirituality', 'daily_spiritual_reflection', 'daily_self_accounting', [
                ['fa' => 'امروز چه نیتی در کارهایت بود؟', 'en' => 'What intention was behind your actions today?'],
                ['fa' => 'امروز کجا با ارزش‌هایت هم‌خوان بودی؟', 'en' => 'Where were you aligned with your values today?'],
                ['fa' => 'اگر بخواهی امروز را با انصاف نگاه کنی، چه می‌بینی؟', 'en' => 'If you look at today fairly, what do you notice?'],
                ['fa' => 'امروز چه چیزی در درونت ماند که دوست داشتی درباره‌اش فکر کنی؟', 'en' => 'What stayed with you today that you wanted to reflect on?'],
            ]),
            $this->template('study', 'مطالعه', 'Study', 'daily_study_reflection', 'daily_learning', [
                ['fa' => 'امروز چه چیزی یاد گرفتی، حتی کوچک؟', 'en' => 'What did you learn today, even something small?'],
                ['fa' => 'چقدر برای یادگیری وقت گذاشتی؟', 'en' => 'How much time did you give to learning today?'],
                ['fa' => 'اگر فردا فقط ۲۰ دقیقه مطالعه کنی، روی چه موضوعی؟', 'en' => 'If you studied for 20 minutes tomorrow, on what?'],
                ['fa' => 'امروز کنجکاوی‌ات کجا بیدار شد؟', 'en' => 'Where did your curiosity wake up today?'],
            ]),
            $this->template('self', 'خودشناسی', 'Self-knowledge', 'daily_self_reflection', 'daily_self_awareness', [
                ['fa' => 'امروز چه چیزی را می‌توانستی بهتر انجام بدهی؟', 'en' => 'What could you have done a little better today?'],
                ['fa' => 'امروز چه احساسی بیشتر از بقیه با تو ماند؟', 'en' => 'Which feeling stayed with you most today?'],
                ['fa' => 'کجا امروز با خودت صادق بودی؟', 'en' => 'Where were you honest with yourself today?'],
                ['fa' => 'یک الگوی کوچک در رفتار امروزت دیدی؟', 'en' => 'Did you notice a small pattern in how you acted today?'],
            ]),
            $this->template('sport', 'ورزش', 'Sport', 'daily_sport_reflection', 'daily_movement', [
                ['fa' => 'امروز بدنت چقدر حرکت کرد؟', 'en' => 'How much did your body move today?'],
                ['fa' => 'یک حرکت کوچک برای فردا چه می‌تواند باشد؟', 'en' => 'What is one small movement you could do tomorrow?'],
                ['fa' => 'امروز ورزش را به چشم تنبیه دیدی یا مراقبت؟', 'en' => 'Did movement feel like punishment or care today?'],
                ['fa' => 'چه چیزی کمکت کرد حرکت کنی، یا بازت داشت؟', 'en' => 'What helped you move today, or what got in the way?'],
            ]),
            $this->template('relations', 'روابط', 'Relationships', 'daily_relations_reflection', 'daily_connection', [
                ['fa' => 'امروز در یک رابطه چه چیزی خوب پیش رفت؟', 'en' => 'What went well in a relationship today?'],
                ['fa' => 'کجا خواستی چیزی بگویی و نگفتی؟', 'en' => 'Where did you want to say something and did not?'],
                ['fa' => 'امروز چطور به کسی گوش دادی؟', 'en' => 'How did you listen to someone today?'],
                ['fa' => 'یک قدم کوچک برای بهتر شدن یک رابطه چیست؟', 'en' => 'What is one small step to improve a relationship?'],
            ]),
            $this->template('custom', 'هدف شخصی', 'Personal goal', 'daily_custom_reflection', 'daily_personal_goal', [
                ['fa' => 'امروز برای هدفی که انتخاب کردی چه قدم کوچکی برداشتی؟', 'en' => 'What small step did you take toward your chosen goal today?'],
                ['fa' => 'امروز چه چیزی تو را به هدف نزدیک‌تر کرد؟', 'en' => 'What brought you closer to your goal today?'],
                ['fa' => 'اگر فردا فقط یک کار کوچک برای هدفت بکنی، چیست؟', 'en' => 'If you did one small thing for your goal tomorrow, what would it be?'],
                ['fa' => 'امروز کجا از مسیرت فاصله گرفتی، بدون قضاوت؟', 'en' => 'Where did you drift from your path today, without judgment?'],
            ]),
        ];
    }

    private function template(
        string $slug,
        string $nameFa,
        string $nameEn,
        string $questionKey,
        string $intent,
        array $pairs
    ): array {
        $variants = [];
        foreach ($pairs as $pair) {
            $variants[] = ['locale' => 'fa', 'body' => $pair['fa'], 'difficulty' => 1];
            $variants[] = ['locale' => 'en', 'body' => $pair['en'], 'difficulty' => 1];
        }

        return [
            'slug' => $slug,
            'name' => $nameFa,
            'description' => $nameEn,
            'is_system' => true,
            'questions' => [[
                'question_key' => $questionKey,
                'intent' => $intent,
                'domain' => $slug,
                'difficulty' => 1,
                'default_frequency' => 'daily',
                'variants' => $variants,
            ]],
        ];
    }
}
