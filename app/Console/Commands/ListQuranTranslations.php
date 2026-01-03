<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListQuranTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quran-translations:list {--compare : مقایسه با جدول translations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'نمایش لیست ترجمه‌های موجود در جدول quran_translations با تعداد آیات';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            // بررسی وجود جدول quran_translations
            if (!DB::getSchemaBuilder()->hasTable('quran_translations')) {
                $this->error('❌ جدول quran_translations در دیتابیس وجود ندارد.');
                return 1;
            }

            $this->info("📚 لیست ترجمه‌های موجود در جدول quran_translations\n");

            // دریافت لیست یونیک ترجمه‌ها بر اساس language و translator_name
            $translations = DB::table('quran_translations')
                ->select(
                    'language',
                    'translator_name',
                    'translate_full_name',
                    DB::raw('COUNT(DISTINCT CONCAT(sura, "-", aya)) as ayat_count'),
                    DB::raw('MIN(id) as first_id'),
                    DB::raw('MAX(id) as last_id')
                )
                ->whereNotNull('language')
                ->whereNotNull('translator_name')
                ->groupBy('language', 'translator_name', 'translate_full_name')
                ->orderBy('language')
                ->orderBy('translator_name')
                ->get();

            if ($translations->isEmpty()) {
                $this->warn('⚠️ هیچ ترجمه‌ای در جدول quran_translations یافت نشد.');
                return 0;
            }

            $this->info("✅ تعداد ترجمه‌های یونیک: {$translations->count()}\n");

            // نمایش لیست ترجمه‌ها
            $tableData = [];
            foreach ($translations as $index => $translation) {
                $language = $translation->language ?? 'نامشخص';
                $translator = $translation->translator_name ?? 'نامشخص';
                $fullName = $translation->translate_full_name ?? 'نامشخص';
                $ayatCount = $translation->ayat_count ?? 0;
                
                $tableData[] = [
                    $index + 1,
                    $language,
                    $translator,
                    $fullName,
                    number_format($ayatCount),
                    $ayatCount == 6236 ? '✅ کامل' : ($ayatCount > 6000 ? '⚠️ ناقص' : '❌ ناقص'),
                ];
            }

            $this->table(
                ['#', 'زبان', 'مترجم', 'نام کامل', 'تعداد آیات', 'وضعیت'],
                $tableData
            );

            // نمایش خلاصه بر اساس زبان
            $this->info("\n📊 خلاصه بر اساس زبان:\n");
            $languageSummary = DB::table('quran_translations')
                ->select(
                    'language',
                    DB::raw('COUNT(DISTINCT translator_name) as translator_count'),
                    DB::raw('SUM(DISTINCT CASE WHEN CONCAT(sura, "-", aya) IS NOT NULL THEN 1 ELSE 0 END) as total_ayats')
                )
                ->whereNotNull('language')
                ->whereNotNull('translator_name')
                ->groupBy('language')
                ->orderBy('language')
                ->get();

            $summaryData = [];
            foreach ($languageSummary as $lang) {
                $summaryData[] = [
                    $lang->language ?? 'نامشخص',
                    $lang->translator_count ?? 0,
                ];
            }

            $this->table(
                ['زبان', 'تعداد مترجم'],
                $summaryData
            );

            // مقایسه با جدول translations در صورت درخواست
            if ($this->option('compare')) {
                $this->compareWithTranslationsTable($translations);
            }

            // نمایش ترجمه‌های کامل (6236 آیه)
            $completeTranslations = $translations->filter(function ($translation) {
                return $translation->ayat_count == 6236;
            });

            if ($completeTranslations->count() > 0) {
                $this->info("\n✅ ترجمه‌های کامل (6236 آیه): {$completeTranslations->count()} ترجمه\n");
                foreach ($completeTranslations as $translation) {
                    $this->line("  • {$translation->language} - {$translation->translator_name} ({$translation->translate_full_name})");
                }
            }

            // نمایش ترجمه‌های ناقص
            $incompleteTranslations = $translations->filter(function ($translation) {
                return $translation->ayat_count < 6236;
            });

            if ($incompleteTranslations->count() > 0) {
                $this->warn("\n⚠️ ترجمه‌های ناقص: {$incompleteTranslations->count()} ترجمه\n");
                foreach ($incompleteTranslations as $translation) {
                    $missing = 6236 - $translation->ayat_count;
                    $this->line("  • {$translation->language} - {$translation->translator_name}: {$translation->ayat_count} آیه ({$missing} آیه کم دارد)");
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ خطا در دریافت لیست ترجمه‌ها: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }

    /**
     * مقایسه با جدول translations
     * 
     * @param \Illuminate\Support\Collection $quranTranslations
     * @return void
     */
    private function compareWithTranslationsTable($quranTranslations): void
    {
        if (!DB::getSchemaBuilder()->hasTable('translations')) {
            $this->warn("\n⚠️ جدول translations وجود ندارد. مقایسه انجام نشد.");
            return;
        }

        $this->info("\n🔄 مقایسه با جدول translations:\n");

        // دریافت ترجمه‌های موجود در جدول translations
        $availableTranslations = DB::table('translations')
            ->select('language', 'translator', 'name', 'filename')
            ->get()
            ->map(function ($translation) {
                // استخراج کد زبان از filename (مثلاً am.sadiq -> am)
                $filename = $translation->filename ?? '';
                $locale = $translation->locale ?? '';
                
                // تلاش برای استخراج translator_name از filename
                $translatorCode = '';
                if (preg_match('/\/([a-z]+)\.([a-z0-9]+)$/i', $filename, $matches)) {
                    $translatorCode = $matches[2] ?? '';
                }
                
                return [
                    'language' => $translation->language ?? '',
                    'translator' => $translation->translator ?? '',
                    'translator_code' => $translatorCode,
                    'name' => $translation->name ?? '',
                    'filename' => $filename,
                    'locale' => $locale,
                ];
            });

        // مقایسه
        $matched = [];
        $notInTranslations = [];

        foreach ($quranTranslations as $quranTranslation) {
            $found = false;
            $quranLang = strtolower($quranTranslation->language ?? '');
            $quranTranslator = strtolower($quranTranslation->translator_name ?? '');

            foreach ($availableTranslations as $available) {
                $availLang = strtolower($available['language'] ?? '');
                $availTranslator = strtolower($available['translator'] ?? '');
                $availCode = strtolower($available['translator_code'] ?? '');

                // مقایسه زبان و مترجم
                if (
                    ($quranLang == $availLang || 
                     $quranLang == $available['locale'] ||
                     $quranLang == $availCode) &&
                    ($quranTranslator == $availTranslator || 
                     $quranTranslator == $availCode ||
                     stripos($availTranslator, $quranTranslator) !== false ||
                     stripos($quranTranslator, $availTranslator) !== false)
                ) {
                    $matched[] = [
                        'quran' => "{$quranTranslation->language} - {$quranTranslation->translator_name}",
                        'available' => "{$available['language']} - {$available['name']}",
                        'filename' => $available['filename'],
                    ];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $notInTranslations[] = [
                    'language' => $quranTranslation->language ?? 'نامشخص',
                    'translator' => $quranTranslation->translator_name ?? 'نامشخص',
                    'full_name' => $quranTranslation->translate_full_name ?? 'نامشخص',
                    'ayat_count' => $quranTranslation->ayat_count ?? 0,
                ];
            }
        }

        // نمایش نتایج
        if (count($matched) > 0) {
            $this->info("✅ ترجمه‌های موجود در هر دو جدول: " . count($matched) . "\n");
            $matchedData = [];
            foreach ($matched as $match) {
                $matchedData[] = [
                    $match['quran'],
                    $match['available'],
                ];
            }
            $this->table(['در quran_translations', 'در translations'], $matchedData);
        }

        if (count($notInTranslations) > 0) {
            $this->warn("\n⚠️ ترجمه‌های موجود در quran_translations اما نه در translations: " . count($notInTranslations) . "\n");
            $notInData = [];
            foreach ($notInTranslations as $notIn) {
                $notInData[] = [
                    $notIn['language'],
                    $notIn['translator'],
                    $notIn['full_name'],
                    number_format($notIn['ayat_count']),
                ];
            }
            $this->table(['زبان', 'مترجم', 'نام کامل', 'تعداد آیات'], $notInData);
        }
    }
}
