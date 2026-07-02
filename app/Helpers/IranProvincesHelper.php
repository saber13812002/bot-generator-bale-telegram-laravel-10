<?php

namespace App\Helpers;

class IranProvincesHelper
{
    /**
     * @return array<int, string> index => province name
     */
    public static function all(): array
    {
        return [
            0 => 'آذربایجان شرقی',
            1 => 'آذربایجان غربی',
            2 => 'اردبیل',
            3 => 'اصفهان',
            4 => 'البرز',
            5 => 'ایلام',
            6 => 'بوشهر',
            7 => 'تهران',
            8 => 'چهارمحال و بختیاری',
            9 => 'خراسان جنوبی',
            10 => 'خراسان رضوی',
            11 => 'خراسان شمالی',
            12 => 'خوزستان',
            13 => 'زنجان',
            14 => 'سمنان',
            15 => 'سیستان و بلوچستان',
            16 => 'فارس',
            17 => 'قزوین',
            18 => 'قم',
            19 => 'کردستان',
            20 => 'کرمان',
            21 => 'کرمانشاه',
            22 => 'کهگیلویه و بویراحمد',
            23 => 'گلستان',
            24 => 'گیلان',
            25 => 'لرستان',
            26 => 'مازندران',
            27 => 'مرکزی',
            28 => 'هرمزگان',
            29 => 'همدان',
            30 => 'یزد',
        ];
    }

    public static function nameByIndex(int $index): ?string
    {
        return self::all()[$index] ?? null;
    }

    public static function indexByName(string $name): ?int
    {
        $index = array_search($name, self::all(), true);

        return $index === false ? null : $index;
    }
}
