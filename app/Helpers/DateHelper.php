<?php

namespace App\Helpers;

use Hekmatinasser\Verta\Verta;
use Carbon\Carbon;

class DateHelper
{
    /**
     * تبدیل تاریخ میلادی به شمسی
     */
    public static function toShamsi($date, string $format = 'Y/m/d'): string
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        
        return Verta::instance($date)->format($format);
    }

    /**
     * تبدیل تاریخ میلادی به قمری (هجری قمری)
     * استفاده از الگوریتم ساده برای تبدیل
     */
    public static function toHijri($date, string $format = 'Y/m/d'): string
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        
        // الگوریتم تبدیل میلادی به قمری
        $jd = self::gregorianToJulian($date->year, $date->month, $date->day);
        $hijri = self::julianToHijri($jd);
        
        // فرمت‌بندی
        if ($format === 'Y/m/d') {
            return sprintf('%d/%02d/%02d', $hijri['year'], $hijri['month'], $hijri['day']);
        } elseif ($format === 'Y-m-d') {
            return sprintf('%d-%02d-%02d', $hijri['year'], $hijri['month'], $hijri['day']);
        } elseif ($format === 'd F Y') {
            $monthNames = [
                1 => 'محرم', 2 => 'صفر', 3 => 'ربیع‌الاول', 4 => 'ربیع‌الثانی',
                5 => 'جمادی‌الاول', 6 => 'جمادی‌الثانی', 7 => 'رجب',
                8 => 'شعبان', 9 => 'رمضان', 10 => 'شوال',
                11 => 'ذی‌القعدة', 12 => 'ذی‌الحجة'
            ];
            return sprintf('%d %s %d', $hijri['day'], $monthNames[$hijri['month']] ?? '', $hijri['year']);
        }
        
        return sprintf('%d/%02d/%02d', $hijri['year'], $hijri['month'], $hijri['day']);
    }

    /**
     * تبدیل میلادی به جولیان
     */
    protected static function gregorianToJulian(int $year, int $month, int $day): float
    {
        if ($month < 3) {
            $year--;
            $month += 12;
        }
        
        $a = intval($year / 100);
        $b = 2 - $a + intval($a / 4);
        
        return intval(365.25 * ($year + 4716)) + intval(30.6001 * ($month + 1)) + $day + $b - 1524.5;
    }

    /**
     * تبدیل جولیان به هجری قمری
     */
    protected static function julianToHijri(float $jd): array
    {
        $jd = $jd + 0.5;
        $z = intval($jd);
        $f = $jd - $z;
        
        if ($z < 2299161) {
            $a = $z;
        } else {
            $alpha = intval(($z - 1867216.25) / 36524.25);
            $a = $z + 1 + $alpha - intval($alpha / 4);
        }
        
        $b = $a + 1524;
        $c = intval(($b - 122.1) / 365.25);
        $d = intval(365.25 * $c);
        $e = intval(($b - $d) / 30.6001);
        
        $day = $b - $d - intval(30.6001 * $e) + $f;
        $month = ($e < 14) ? $e - 1 : $e - 13;
        $year = ($month < 3) ? $c - 4715 : $c - 4716;
        
        // تبدیل به هجری قمری
        $jd0 = self::gregorianToJulian(622, 7, 16); // اول محرم سال 1 هجری
        $daysSinceHijri = intval($jd - $jd0);
        
        $hijriYear = intval(($daysSinceHijri / 354.367) + 1);
        $hijriMonth = intval((($daysSinceHijri % 354.367) / 29.5306) + 1);
        $hijriDay = intval((($daysSinceHijri % 354.367) % 29.5306) + 1);
        
        // تصحیح ماه و روز
        if ($hijriMonth > 12) {
            $hijriYear++;
            $hijriMonth -= 12;
        }
        
        if ($hijriDay > 30) {
            $hijriMonth++;
            $hijriDay -= 30;
        }
        
        if ($hijriMonth > 12) {
            $hijriYear++;
            $hijriMonth -= 12;
        }
        
        return [
            'year' => $hijriYear,
            'month' => $hijriMonth,
            'day' => $hijriDay
        ];
    }

    /**
     * دریافت نام ماه شمسی
     */
    public static function getShamsiMonthName(int $month): string
    {
        $months = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
            4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
            7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
            10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
        ];
        
        return $months[$month] ?? '';
    }

    /**
     * دریافت نام ماه قمری
     */
    public static function getHijriMonthName(int $month): string
    {
        $months = [
            1 => 'محرم', 2 => 'صفر', 3 => 'ربیع‌الاول', 4 => 'ربیع‌الثانی',
            5 => 'جمادی‌الاول', 6 => 'جمادی‌الثانی', 7 => 'رجب',
            8 => 'شعبان', 9 => 'رمضان', 10 => 'شوال',
            11 => 'ذی‌القعدة', 12 => 'ذی‌الحجة'
        ];
        
        return $months[$month] ?? '';
    }
}
