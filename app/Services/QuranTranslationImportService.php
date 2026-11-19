<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuranTranslationImportService
{
    /**
     * پارس کردن فایل SQL و استخراج داده‌ها
     * 
     * @param string $filePath مسیر فایل SQL
     * @return array شامل metadata و data
     */
    public function parseSqlFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("فایل {$filePath} وجود ندارد.");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception("خطا در خواندن فایل {$filePath}.");
        }

        // استخراج metadata
        $metadata = $this->extractMetadata($content, basename($filePath));

        // استخراج نام جدول از SQL
        $tableName = $this->extractTableName($content);
        if (!$tableName) {
            throw new Exception("نام جدول در فایل SQL یافت نشد.");
        }

        // استخراج داده‌ها از INSERT statements
        $data = $this->extractInsertData($content, $tableName);

        return [
            'metadata' => $metadata,
            'table_name' => $tableName,
            'data' => $data,
        ];
    }

    /**
     * استخراج metadata از کامنت‌های SQL یا نام فایل
     * 
     * @param string $content محتوای فایل SQL
     * @param string $fileName نام فایل
     * @return array
     */
    public function extractMetadata(string $content, string $fileName): array
    {
        $metadata = [
            'language' => null,
            'translator_name' => null,
            'translate_full_name' => null,
            'name' => null,
            'translator' => null,
        ];

        // اولویت اول: استخراج از ID (که کد زبان را دارد)
        if (preg_match('/#\s*ID:\s*(.+)/i', $content, $matches)) {
            $id = trim($matches[1]);
            $parts = explode('.', $id);
            if (count($parts) >= 2) {
                $metadata['language'] = $parts[0];
                $metadata['translator_name'] = implode('.', array_slice($parts, 1));
                $metadata['translate_full_name'] = $id;
            }
        }

        // استخراج از کامنت‌های SQL (فقط اگر ID پیدا نشد)
        if (!$metadata['language'] && preg_match('/#\s*Language:\s*(.+)/i', $content, $matches)) {
            $languageFullName = trim($matches[1]);
            // تبدیل نام کامل زبان به کد زبان
            $metadata['language'] = $this->convertLanguageNameToCode($languageFullName);
        }
        if (preg_match('/#\s*Translator:\s*(.+)/i', $content, $matches)) {
            $metadata['translator'] = trim($matches[1]);
        }
        if (preg_match('/#\s*Name:\s*(.+)/i', $content, $matches)) {
            $metadata['name'] = trim($matches[1]);
        }

        // استخراج از نام جدول در SQL (مثلاً zh_jian) - اولویت دوم
        if ((!$metadata['language'] || !$metadata['translator_name']) && preg_match('/CREATE TABLE\s+[`"]?(\w+)[`"]?/i', $content, $matches)) {
            $tableName = $matches[1];
            if (strpos($tableName, '_') !== false) {
                $parts = explode('_', $tableName);
                if (count($parts) >= 2) {
                    $metadata['language'] = $metadata['language'] ?? $parts[0];
                    $metadata['translator_name'] = $metadata['translator_name'] ?? implode('_', array_slice($parts, 1));
                    $metadata['translate_full_name'] = $metadata['translate_full_name'] ?? str_replace('_', '.', $tableName);
                }
            }
        }

        // استخراج از نام فایل (مثلاً zh.jian.sql) - اولویت سوم
        if (!$metadata['language'] || !$metadata['translator_name']) {
            $fileNameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
            if (strpos($fileNameWithoutExt, '.') !== false) {
                $parts = explode('.', $fileNameWithoutExt);
                if (count($parts) >= 2) {
                    $metadata['language'] = $metadata['language'] ?? $parts[0];
                    $metadata['translator_name'] = $metadata['translator_name'] ?? implode('.', array_slice($parts, 1));
                    $metadata['translate_full_name'] = $metadata['translate_full_name'] ?? $fileNameWithoutExt;
                }
            }
        }

        return $metadata;
    }

    /**
     * تبدیل نام کامل زبان به کد زبان
     * 
     * @param string $languageName نام کامل زبان (مثلاً "Chinese", "Spanish")
     * @return string کد زبان (مثلاً "zh", "es")
     */
    private function convertLanguageNameToCode(string $languageName): string
    {
        $mapping = [
            'Albanian' => 'sq',
            'Amazigh' => 'ber',
            'Amharic' => 'am',
            'Arabic' => 'ar',
            'Azerbaijani' => 'az',
            'Bengali' => 'bn',
            'Bosnian' => 'bs',
            'Chinese' => 'zh',
            'English' => 'en',
            'French' => 'fr',
            'German' => 'de',
            'Hausa' => 'ha',
            'Hindi' => 'hi',
            'Italian' => 'it',
            'Spanish' => 'es',
            'Turkish' => 'tr',
            'Urdu' => 'ur',
        ];

        $languageName = trim($languageName);
        
        // اگر کد زبان است (2-3 حرف)، همان را برمی‌گردانیم
        if (strlen($languageName) <= 3 && ctype_alpha($languageName)) {
            return strtolower($languageName);
        }

        // تبدیل نام کامل به کد
        return $mapping[$languageName] ?? strtolower(substr($languageName, 0, 2));
    }

    /**
     * استخراج نام جدول از SQL
     * 
     * @param string $content
     * @return string|null
     */
    private function extractTableName(string $content): ?string
    {
        // استفاده از [\w-]+ برای پشتیبانی از خط تیره در نام جدول
        if (preg_match('/CREATE TABLE\s+[`"]?([\w-]+)[`"]?/i', $content, $matches)) {
            return $matches[1];
        }
        if (preg_match('/INSERT INTO\s+[`"]?([\w-]+)[`"]?/i', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * استخراج داده‌ها از INSERT statements
     * 
     * @param string $content
     * @param string $tableName
     * @return array
     */
    private function extractInsertData(string $content, string $tableName): array
    {
        $data = [];
        
        // پیدا کردن همه INSERT statements
        // Pattern برای پیدا کردن INSERT INTO ... VALUES ... ;
        // استفاده از lookahead برای پیدا کردن `;` که بعد از VALUES است
        $escapedTableName = preg_quote($tableName, '/');
        $pattern = '/INSERT INTO\s+[`"]?' . $escapedTableName . '[`"]?\s*\([^)]+\)\s*VALUES\s*(.+?)(?=;\s*(?:--|\n|INSERT|$))/is';
        
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $valuesString = trim($match[1]);
                // حذف `;` از انتها اگر وجود دارد
                $valuesString = rtrim($valuesString, ';');
                // پارس کردن VALUES
                $rows = $this->parseValuesString($valuesString);
                $data = array_merge($data, $rows);
            }
        } else {
            // اگر pattern کار نکرد، از روش جایگزین استفاده می‌کنیم
            // پیدا کردن همه INSERT statements و استخراج VALUES تا `;`
            $lines = explode("\n", $content);
            $inInsert = false;
            $currentValues = '';
            $insertCount = 0;
            
            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                
                // شروع INSERT statement
                if (preg_match('/INSERT INTO\s+[`"]?' . $escapedTableName . '[`"]?\s*\([^)]+\)\s*VALUES\s*(.*)/i', $trimmedLine, $m)) {
                    $inInsert = true;
                    $currentValues = $m[1] ?? '';
                    $insertCount++;
                } elseif ($inInsert) {
                    // ادامه VALUES
                    $currentValues .= ' ' . $trimmedLine;
                    
                    // پایان INSERT statement (پیدا کردن `;`)
                    if (strpos($trimmedLine, ';') !== false) {
                        // حذف `;` و کامنت‌ها
                        $currentValues = preg_replace('/;\s*--.*$/', '', $currentValues);
                        $currentValues = rtrim($currentValues, ';');
                        
                        // پارس کردن VALUES
                        $rows = $this->parseValuesString($currentValues);
                        $data = array_merge($data, $rows);
                        
                        $inInsert = false;
                        $currentValues = '';
                    }
                }
            }
        }

        return $data;
    }

    /**
     * پارس کردن رشته VALUES
     * 
     * @param string $valuesString
     * @return array
     */
    private function parseValuesString(string $valuesString): array
    {
        $rows = [];
        $currentRow = '';
        $depth = 0;
        $inString = false;
        $escapeNext = false;
        $stringChar = null;

        for ($i = 0; $i < strlen($valuesString); $i++) {
            $char = $valuesString[$i];

            if ($escapeNext) {
                $currentRow .= $char;
                $escapeNext = false;
                continue;
            }

            if (($char === '"' || $char === "'") && !$inString) {
                $inString = true;
                $stringChar = $char;
                $currentRow .= $char;
            } elseif ($char === $stringChar && $inString) {
                $inString = false;
                $stringChar = null;
                $currentRow .= $char;
            } elseif ($char === '\\' && $inString) {
                $escapeNext = true;
                $currentRow .= $char;
            } elseif ($char === '(' && !$inString) {
                $depth++;
                if ($depth === 1) {
                    $currentRow = '';
                    continue;
                }
                $currentRow .= $char;
            } elseif ($char === ')' && !$inString) {
                $depth--;
                if ($depth === 0) {
                    // پایان یک ردیف
                    $row = $this->parseRow($currentRow);
                    if ($row) {
                        $rows[] = $row;
                    }
                    $currentRow = '';
                    continue;
                }
                $currentRow .= $char;
            } else {
                $currentRow .= $char;
            }
        }

        return $rows;
    }

    /**
     * پارس کردن یک ردیف داده
     * 
     * @param string $rowString
     * @return array|null
     */
    private function parseRow(string $rowString): ?array
    {
        // حذف فاصله‌های اضافی
        $rowString = trim($rowString);
        if (empty($rowString)) {
            return null;
        }

        // تقسیم بر اساس کاما
        $values = [];
        $currentValue = '';
        $inString = false;
        $stringChar = null;
        $escapeNext = false;

        for ($i = 0; $i < strlen($rowString); $i++) {
            $char = $rowString[$i];

            if ($escapeNext) {
                $currentValue .= $char;
                $escapeNext = false;
                continue;
            }

            if (($char === '"' || $char === "'") && !$inString) {
                $inString = true;
                $stringChar = $char;
                $currentValue .= $char;
            } elseif ($char === $stringChar && $inString) {
                $inString = false;
                $stringChar = null;
                $currentValue .= $char;
            } elseif ($char === '\\' && $inString) {
                $escapeNext = true;
                $currentValue .= $char;
            } elseif ($char === ',' && !$inString) {
                $values[] = $this->cleanValue($currentValue);
                $currentValue = '';
            } else {
                $currentValue .= $char;
            }
        }

        if (!empty($currentValue)) {
            $values[] = $this->cleanValue($currentValue);
        }

        // انتظار می‌رود که 4 مقدار داشته باشیم: index, sura, aya, text
        if (count($values) >= 4) {
            return [
                'index' => (int) trim($values[0]),
                'sura' => (int) trim($values[1]),
                'aya' => (int) trim($values[2]),
                'text' => $this->unescapeString(trim($values[3])),
            ];
        }

        return null;
    }

    /**
     * پاک کردن مقدار (حذف کوتیشن‌ها)
     * 
     * @param string $value
     * @return string
     */
    private function cleanValue(string $value): string
    {
        $value = trim($value);
        if (($value[0] === '"' && substr($value, -1) === '"') ||
            ($value[0] === "'" && substr($value, -1) === "'")) {
            return substr($value, 1, -1);
        }
        return $value;
    }

    /**
     * Unescape string
     * 
     * @param string $str
     * @return string
     */
    private function unescapeString(string $str): string
    {
        return str_replace(['\\"', "\\'", '\\\\'], ['"', "'", '\\'], $str);
    }

    /**
     * تبدیل ساختار داده به فرمت quran_translations
     * 
     * @param array $sqlData داده‌های استخراج شده از SQL
     * @param array $metadata اطلاعات metadata
     * @return array
     */
    public function transformData(array $sqlData, array $metadata): array
    {
        $transformed = [];

        foreach ($sqlData as $row) {
            // بررسی صحت داده‌ها
            if (!isset($row['sura']) || !isset($row['aya']) || !isset($row['text'])) {
                continue;
            }

            // بررسی محدوده sura (1-114)
            if ($row['sura'] < 1 || $row['sura'] > 114) {
                continue;
            }

            // بررسی محدوده aya (> 0)
            if ($row['aya'] < 1) {
                continue;
            }

            $transformed[] = [
                'translation_id' => null, // می‌تواند بعداً تنظیم شود
                'language' => $metadata['language'],
                'translator_name' => $metadata['translator_name'],
                'translate_full_name' => $metadata['translate_full_name'],
                'index' => $row['index'] ?? null,
                'sura' => $row['sura'],
                'aya' => $row['aya'],
                'text' => $row['text'],
            ];
        }

        return $transformed;
    }

    /**
     * بررسی وجود ترجمه در دیتابیس
     * 
     * @param string $language
     * @param string $translator
     * @return bool
     */
    public function checkIfExists(string $language, string $translator): bool
    {
        return DB::table('quran_translations')
            ->where('language', $language)
            ->where('translator_name', $translator)
            ->exists();
    }

    /**
     * دریافت تعداد آیات موجود برای یک ترجمه
     * 
     * @param string $language
     * @param string $translator
     * @return int
     */
    public function getExistingAyatCount(string $language, string $translator): int
    {
        return DB::table('quran_translations')
            ->where('language', $language)
            ->where('translator_name', $translator)
            ->distinct()
            ->count(DB::raw('CONCAT(sura, "-", aya)'));
    }

    /**
     * بررسی کامل بودن ترجمه
     * 
     * @param string $language
     * @param string $translator
     * @return array ['is_complete' => bool, 'ayat_count' => int, 'percentage' => float, 'missing_ayats' => int]
     */
    public function checkTranslationCompleteness(string $language, string $translator): array
    {
        $ayatCount = $this->getExistingAyatCount($language, $translator);
        $isComplete = $ayatCount == 6236;
        $percentage = $ayatCount > 0 ? round(($ayatCount / 6236) * 100, 1) : 0;
        
        return [
            'is_complete' => $isComplete,
            'ayat_count' => $ayatCount,
            'percentage' => $percentage,
            'missing_ayats' => 6236 - $ayatCount,
        ];
    }

    /**
     * ایمپورت داده‌ها به دیتابیس
     * 
     * @param array $data داده‌های تبدیل شده
     * @param bool $force بازنویسی ترجمه موجود
     * @return array نتیجه شامل تعداد موفق، خطاها و غیره
     */
    public function import(array $data, bool $force = false): array
    {
        if (empty($data)) {
            return [
                'success' => false,
                'message' => 'هیچ داده‌ای برای ایمپورت وجود ندارد.',
                'inserted' => 0,
                'errors' => [],
            ];
        }

        $metadata = $data[0] ?? null;
        if (!$metadata) {
            return [
                'success' => false,
                'message' => 'metadata یافت نشد.',
                'inserted' => 0,
                'errors' => [],
            ];
        }

        $language = $metadata['language'];
        $translator = $metadata['translator_name'];

        // بررسی وجود ترجمه
        $exists = $this->checkIfExists($language, $translator);
        if ($exists && !$force) {
            return [
                'success' => false,
                'message' => "ترجمه {$language}.{$translator} قبلاً ایمپورت شده است. از --force استفاده کنید.",
                'inserted' => 0,
                'errors' => [],
            ];
        }

        // اگر force است و ترجمه وجود دارد، حذف می‌کنیم
        if ($exists && $force) {
            DB::table('quran_translations')
                ->where('language', $language)
                ->where('translator_name', $translator)
                ->delete();
        }

        $inserted = 0;
        $errors = [];
        $batchSize = 1000;

        try {
            DB::beginTransaction();

            // تقسیم داده‌ها به batch
            $batches = array_chunk($data, $batchSize);

            foreach ($batches as $batch) {
                try {
                    DB::table('quran_translations')->insert($batch);
                    $inserted += count($batch);
                } catch (Exception $e) {
                    $errors[] = [
                        'batch' => count($batch),
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Error importing batch', [
                        'error' => $e->getMessage(),
                        'language' => $language,
                        'translator' => $translator,
                    ]);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => "ایمپورت با موفقیت انجام شد.",
                'inserted' => $inserted,
                'total' => count($data),
                'errors' => $errors,
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error importing translation', [
                'error' => $e->getMessage(),
                'language' => $language,
                'translator' => $translator,
            ]);

            return [
                'success' => false,
                'message' => "خطا در ایمپورت: " . $e->getMessage(),
                'inserted' => $inserted,
                'errors' => array_merge($errors, [['error' => $e->getMessage()]]),
            ];
        }
    }
}
