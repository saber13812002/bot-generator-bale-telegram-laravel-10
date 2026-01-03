<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TranslationsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('translations')->delete();
        
        \DB::table('translations')->insert(array (
            0 => 
            array (
                'language' => 'Albanian',
                'name' => 'Efendi Nahi',
                'translator' => 'Hasan Efendi Nahi',
                'filename' => 'https://tanzil.net/trans/sq.nahi',
            ),
            1 => 
            array (
                'language' => 'Albanian',
                'name' => 'Feti Mehdiu',
                'translator' => 'Feti Mehdiu',
                'filename' => 'https://tanzil.net/trans/sq.mehdiu',
            ),
            2 => 
            array (
                'language' => 'Albanian',
                'name' => 'Sherif Ahmeti',
                'translator' => 'Sherif Ahmeti',
                'filename' => 'https://tanzil.net/trans/sq.ahmeti',
            ),
            3 => 
            array (
                'language' => 'Amazigh',
                'name' => 'At Mensur',
                'translator' => 'Ramdane At Mansour *',
                'filename' => 'https://tanzil.net/trans/ber.mensur',
            ),
            4 => 
            array (
                'language' => 'Arabic',
                'name' => 'تفسير الجلالين',
                'translator' => 'Jalal ad-Din al-Mahalli and Jalal ad-Din as-Suyuti *',
                'filename' => 'https://tanzil.net/trans/ar.jalalayn',
            ),
            5 => 
            array (
                'language' => 'Arabic',
                'name' => 'تفسير المیسر',
                'translator' => 'King Fahad Quran Complex *',
                'filename' => 'https://tanzil.net/trans/ar.muyassar',
            ),
            6 => 
            array (
                'language' => 'Amharic',
                'name' => 'ሳዲቅ & ሳኒ ሐቢብ',
                'translator' => 'Muhammed Sadiq and Muhammed Sani Habib *',
                'filename' => 'https://tanzil.net/trans/am.sadiq',
            ),
            7 => 
            array (
                'language' => 'Azerbaijani',
                'name' => 'Məmmədəliyev & Bünyadov',
                'translator' => 'Vasim Mammadaliyev and Ziya Bunyadov * †',
                'filename' => 'https://tanzil.net/trans/az.mammadaliyev',
            ),
            8 => 
            array (
                'language' => 'Azerbaijani',
                'name' => 'Musayev',
                'translator' => 'Alikhan Musayev',
                'filename' => 'https://tanzil.net/trans/az.musayev',
            ),
            9 => 
            array (
                'language' => 'Bengali',
                'name' => 'জহুরুল হক',
                'translator' => 'Zohurul Hoque *',
                'filename' => 'https://tanzil.net/trans/bn.hoque',
            ),
            10 => 
            array (
                'language' => 'Bengali',
                'name' => 'মুহিউদ্দীন খান',
                'translator' => 'Muhiuddin Khan',
                'filename' => 'https://tanzil.net/trans/bn.bengali',
            ),
            11 => 
            array (
                'language' => 'Bosnian',
                'name' => 'Korkut',
                'translator' => 'Besim Korkut *',
                'filename' => 'https://tanzil.net/trans/bs.korkut',
            ),
            12 => 
            array (
                'language' => 'Bosnian',
                'name' => 'Mlivo',
                'translator' => 'Mustafa Mlivo',
                'filename' => 'https://tanzil.net/trans/bs.mlivo',
            ),
            13 => 
            array (
                'language' => 'Bulgarian',
                'name' => 'Теофанов',
                'translator' => 'Tzvetan Theophanov',
                'filename' => 'https://tanzil.net/trans/bg.theophanov',
            ),
            14 => 
            array (
                'language' => 'Chinese',
                'name' => 'Ma Jian',
                'translator' => 'Ma Jian',
                'filename' => 'https://tanzil.net/trans/zh.jian',
            ),
            15 => 
            array (
                'language' => 'Chinese',
            'name' => 'Ma Jian (Traditional)',
                'translator' => 'Ma Jian',
                'filename' => 'https://tanzil.net/trans/zh.majian',
            ),
            16 => 
            array (
                'language' => 'Czech',
                'name' => 'Hrbek',
                'translator' => 'Preklad I. Hrbek',
                'filename' => 'https://tanzil.net/trans/cs.hrbek',
            ),
            17 => 
            array (
                'language' => 'Czech',
                'name' => 'Nykl',
                'translator' => 'A. R. Nykl',
                'filename' => 'https://tanzil.net/trans/cs.nykl',
            ),
            18 => 
            array (
                'language' => 'Divehi',
                'name' => 'ދިވެހި',
                'translator' => 'Office of the President of Maldives',
                'filename' => 'https://tanzil.net/trans/dv.divehi',
            ),
            19 => 
            array (
                'language' => 'Dutch',
                'name' => 'Keyzer',
                'translator' => 'Salomo Keyzer',
                'filename' => 'https://tanzil.net/trans/nl.keyzer',
            ),
            20 => 
            array (
                'language' => 'Dutch',
                'name' => 'Leemhuis',
                'translator' => 'Fred Leemhuis',
                'filename' => 'https://tanzil.net/trans/nl.leemhuis',
            ),
            21 => 
            array (
                'language' => 'Dutch',
                'name' => 'Siregar',
                'translator' => 'Sofian S. Siregar',
                'filename' => 'https://tanzil.net/trans/nl.siregar',
            ),
            22 => 
            array (
                'language' => 'English',
                'name' => 'Ahmed Ali',
                'translator' => 'Ahmed Ali *',
                'filename' => 'https://tanzil.net/trans/en.ahmedali',
            ),
            23 => 
            array (
                'language' => 'English',
                'name' => 'Ahmed Raza Khan',
                'translator' => 'Ahmed Raza Khan *',
                'filename' => 'https://tanzil.net/trans/en.ahmedraza',
            ),
            24 => 
            array (
                'language' => 'English',
                'name' => 'Arberry',
                'translator' => 'A. J. Arberry *',
                'filename' => 'https://tanzil.net/trans/en.arberry',
            ),
            25 => 
            array (
                'language' => 'English',
                'name' => 'Daryabadi',
                'translator' => 'Abdul Majid Daryabadi *',
                'filename' => 'https://tanzil.net/trans/en.daryabadi',
            ),
            26 => 
            array (
                'language' => 'English',
                'name' => 'Hilali & Khan',
                'translator' => 'Muhammad Taqi-ud-Din al-Hilali and Muhammad Muhsin Khan *',
                'filename' => 'https://tanzil.net/trans/en.hilali',
            ),
            27 => 
            array (
                'language' => 'English',
                'name' => 'Itani',
                'translator' => 'Talal Itani',
                'filename' => 'https://tanzil.net/trans/en.itani',
            ),
            28 => 
            array (
                'language' => 'English',
                'name' => 'Maududi',
                'translator' => 'Abul Ala Maududi *',
                'filename' => 'https://tanzil.net/trans/en.maududi',
            ),
            29 => 
            array (
                'language' => 'English',
                'name' => 'Mubarakpuri',
                'translator' => 'Safi-ur-Rahman al-Mubarakpuri *',
                'filename' => 'https://tanzil.net/trans/en.mubarakpuri',
            ),
            30 => 
            array (
                'language' => 'English',
                'name' => 'Pickthall',
                'translator' => 'Mohammed Marmaduke William Pickthall *',
                'filename' => 'https://tanzil.net/trans/en.pickthall',
            ),
            31 => 
            array (
                'language' => 'English',
                'name' => 'Qarai',
                'translator' => 'Ali Quli Qarai',
                'filename' => 'https://tanzil.net/trans/en.qarai',
            ),
            32 => 
            array (
                'language' => 'English',
                'name' => 'Qaribullah & Darwish',
                'translator' => 'Hasan al-Fatih Qaribullah and Ahmad Darwish',
                'filename' => 'https://tanzil.net/trans/en.qaribullah',
            ),
            33 => 
            array (
                'language' => 'English',
                'name' => 'Saheeh International',
                'translator' => 'Saheeh International *',
                'filename' => 'https://tanzil.net/trans/en.sahih',
            ),
            34 => 
            array (
                'language' => 'English',
                'name' => 'Sarwar',
                'translator' => 'Muhammad Sarwar *',
                'filename' => 'https://tanzil.net/trans/en.sarwar',
            ),
            35 => 
            array (
                'language' => 'English',
                'name' => 'Shakir',
                'translator' => 'Mohammad Habib Shakir *',
                'filename' => 'https://tanzil.net/trans/en.shakir',
            ),
            36 => 
            array (
                'language' => 'English',
                'name' => 'Transliteration',
                'translator' => 'English Transliteration',
                'filename' => 'https://tanzil.net/trans/en.transliteration',
            ),
            37 => 
            array (
                'language' => 'English',
                'name' => 'Wahiduddin Khan',
                'translator' => 'Wahiduddin Khan *',
                'filename' => 'https://tanzil.net/trans/en.wahiduddin',
            ),
            38 => 
            array (
                'language' => 'English',
                'name' => 'Yusuf Ali',
                'translator' => 'Abdullah Yusuf Ali *',
                'filename' => 'https://tanzil.net/trans/en.yusufali',
            ),
            39 => 
            array (
                'language' => 'French',
                'name' => 'Hamidullah',
                'translator' => 'Muhammad Hamidullah *',
                'filename' => 'https://tanzil.net/trans/fr.hamidullah',
            ),
            40 => 
            array (
                'language' => 'German',
                'name' => 'Abu Rida',
                'translator' => 'Abu Rida Muhammad ibn Ahmad ibn Rassoul',
                'filename' => 'https://tanzil.net/trans/de.aburida',
            ),
            41 => 
            array (
                'language' => 'German',
                'name' => 'Bubenheim & Elyas',
                'translator' => 'A. S. F. Bubenheim and N. Elyas *',
                'filename' => 'https://tanzil.net/trans/de.bubenheim',
            ),
            42 => 
            array (
                'language' => 'German',
                'name' => 'Khoury',
                'translator' => 'Adel Theodor Khoury *',
                'filename' => 'https://tanzil.net/trans/de.khoury',
            ),
            43 => 
            array (
                'language' => 'German',
                'name' => 'Zaidan',
                'translator' => 'Amir Zaidan',
                'filename' => 'https://tanzil.net/trans/de.zaidan',
            ),
            44 => 
            array (
                'language' => 'Hausa',
                'name' => 'Gumi',
                'translator' => 'Abubakar Mahmoud Gumi',
                'filename' => 'https://tanzil.net/trans/ha.gumi',
            ),
            45 => 
            array (
                'language' => 'Hindi',
                'name' => 'फ़ारूक़ ख़ान & अहमद',
                'translator' => 'Muhammad Farooq Khan and Muhammad Ahmed',
                'filename' => 'https://tanzil.net/trans/hi.farooq',
            ),
            46 => 
            array (
                'language' => 'Hindi',
                'name' => 'फ़ारूक़ ख़ान & नदवी',
                'translator' => 'Suhel Farooq Khan and Saifur Rahman Nadwi',
                'filename' => 'https://tanzil.net/trans/hi.hindi',
            ),
            47 => 
            array (
                'language' => 'Indonesian',
                'name' => 'Bahasa Indonesia',
                'translator' => 'Indonesian Ministry of Religious Affairs',
                'filename' => 'https://tanzil.net/trans/id.indonesian',
            ),
            48 => 
            array (
                'language' => 'Indonesian',
                'name' => 'Quraish Shihab',
                'translator' => 'Muhammad Quraish Shihab et al. *',
                'filename' => 'https://tanzil.net/trans/id.muntakhab',
            ),
            49 => 
            array (
                'language' => 'Indonesian',
                'name' => 'Tafsir Jalalayn',
                'translator' => 'Jalal ad-Din al-Mahalli and Jalal ad-Din as-Suyuti *',
                'filename' => 'https://tanzil.net/trans/id.jalalayn',
            ),
            50 => 
            array (
                'language' => 'Italian',
                'name' => 'Piccardo',
                'translator' => 'Hamza Roberto Piccardo *',
                'filename' => 'https://tanzil.net/trans/it.piccardo',
            ),
            51 => 
            array (
                'language' => 'Japanese',
                'name' => 'Japanese',
                'translator' => 'Unknown',
                'filename' => 'https://tanzil.net/trans/ja.japanese',
            ),
            52 => 
            array (
                'language' => 'Korean',
                'name' => 'Korean',
                'translator' => 'Unknown',
                'filename' => 'https://tanzil.net/trans/ko.korean',
            ),
            53 => 
            array (
                'language' => 'Kurdish',
                'name' => 'ته‌فسیری ئاسان',
                'translator' => 'Burhan Muhammad-Amin',
                'filename' => 'https://tanzil.net/trans/ku.asan',
            ),
            54 => 
            array (
                'language' => 'Malay',
                'name' => 'Basmeih',
                'translator' => 'Abdullah Muhammad Basmeih',
                'filename' => 'https://tanzil.net/trans/ms.basmeih',
            ),
            55 => 
            array (
                'language' => 'Malayalam',
                'name' => 'അബ്ദുല്‍ ഹമീദ് & പറപ്പൂര്‍',
                'translator' => 'Cheriyamundam Abdul Hameed and Kunhi Mohammed Parappoor',
                'filename' => 'https://tanzil.net/trans/ml.abdulhameed',
            ),
            56 => 
            array (
                'language' => 'Malayalam',
                'name' => 'കാരകുന്ന് & എളയാവൂര്',
                'translator' => 'Muhammad Karakunnu and Vanidas Elayavoor *',
                'filename' => 'https://tanzil.net/trans/ml.karakunnu',
            ),
            57 => 
            array (
                'language' => 'Norwegian',
                'name' => 'Einar Berg',
                'translator' => 'Einar Berg',
                'filename' => 'https://tanzil.net/trans/no.berg',
            ),
            58 => 
            array (
                'language' => 'Pashto',
                'name' => 'عبدالولي',
                'translator' => 'Abdulwali Khan',
                'filename' => 'https://tanzil.net/trans/ps.abdulwali',
            ),
            59 => 
            array (
                'language' => 'Persian',
                'name' => 'انصاریان',
                'translator' => 'Hussain Ansarian *',
                'filename' => 'https://tanzil.net/trans/fa.ansarian',
            ),
            60 => 
            array (
                'language' => 'Persian',
                'name' => 'آیتی',
                'translator' => 'AbdolMohammad Ayati *',
                'filename' => 'https://tanzil.net/trans/fa.ayati',
            ),
            61 => 
            array (
                'language' => 'Persian',
                'name' => 'بهرام‌پور',
                'translator' => 'Abolfazl Bahrampour *',
                'filename' => 'https://tanzil.net/trans/fa.bahrampour',
            ),
            62 => 
            array (
                'language' => 'Persian',
                'name' => 'قرائتی',
                'translator' => 'Mohsen Gharaati *',
                'filename' => 'https://tanzil.net/trans/fa.gharaati',
            ),
            63 => 
            array (
                'language' => 'Persian',
                'name' => 'الهی قمشه‌ای',
                'translator' => 'Mahdi Elahi Ghomshei *',
                'filename' => 'https://tanzil.net/trans/fa.ghomshei',
            ),
            64 => 
            array (
                'language' => 'Persian',
                'name' => 'خرمدل',
                'translator' => 'Mostafa Khorramdel *',
                'filename' => 'https://tanzil.net/trans/fa.khorramdel',
            ),
            65 => 
            array (
                'language' => 'Persian',
                'name' => 'خرمشاهی',
                'translator' => 'Baha\'oddin Khorramshahi *',
                'filename' => 'https://tanzil.net/trans/fa.khorramshahi',
            ),
            66 => 
            array (
                'language' => 'Persian',
                'name' => 'صادقی تهرانی',
                'translator' => 'Mohammad Sadeqi Tehrani *',
                'filename' => 'https://tanzil.net/trans/fa.sadeqi',
            ),
            67 => 
            array (
                'language' => 'Persian',
                'name' => 'صفوی',
                'translator' => 'Sayyed Mohammad Reza Safavi *',
                'filename' => 'https://tanzil.net/trans/fa.safavi',
            ),
            68 => 
            array (
                'language' => 'Persian',
                'name' => 'فولادوند',
                'translator' => 'Mohammad Mahdi Fooladvand *',
                'filename' => 'https://tanzil.net/trans/fa.fooladvand',
            ),
            69 => 
            array (
                'language' => 'Persian',
                'name' => 'مجتبوی',
                'translator' => 'Sayyed Jalaloddin Mojtabavi *',
                'filename' => 'https://tanzil.net/trans/fa.mojtabavi',
            ),
            70 => 
            array (
                'language' => 'Persian',
                'name' => 'معزی',
                'translator' => 'Mohammad Kazem Moezzi',
                'filename' => 'https://tanzil.net/trans/fa.moezzi',
            ),
            71 => 
            array (
                'language' => 'Persian',
                'name' => 'مکارم شیرازی',
                'translator' => 'Naser Makarem Shirazi *',
                'filename' => 'https://tanzil.net/trans/fa.makarem',
            ),
            72 => 
            array (
                'language' => 'Polish',
                'name' => 'Bielawskiego',
                'translator' => 'Józefa Bielawskiego',
                'filename' => 'https://tanzil.net/trans/pl.bielawskiego',
            ),
            73 => 
            array (
                'language' => 'Portuguese',
                'name' => 'El-Hayek',
                'translator' => 'Samir El-Hayek',
                'filename' => 'https://tanzil.net/trans/pt.elhayek',
            ),
            74 => 
            array (
                'language' => 'Romanian',
                'name' => 'Grigore',
                'translator' => 'George Grigore',
                'filename' => 'https://tanzil.net/trans/ro.grigore',
            ),
            75 => 
            array (
                'language' => 'Russian',
                'name' => 'Абу Адель',
                'translator' => 'Abu Adel',
                'filename' => 'https://tanzil.net/trans/ru.abuadel',
            ),
            76 => 
            array (
                'language' => 'Russian',
                'name' => 'Аль-Мунтахаб',
                'translator' => 'Ministry of Awqaf, Egypt',
                'filename' => 'https://tanzil.net/trans/ru.muntahab',
            ),
            77 => 
            array (
                'language' => 'Russian',
                'name' => 'Калям Шариф',
                'translator' => 'Muslim Religious Board of the Republiс of Tatarstan *',
                'filename' => 'https://tanzil.net/trans/ru.kalam',
            ),
            78 => 
            array (
                'language' => 'Russian',
                'name' => 'Крачковский',
                'translator' => 'Ignaty Yulianovich Krachkovsky *',
                'filename' => 'https://tanzil.net/trans/ru.krachkovsky',
            ),
            79 => 
            array (
                'language' => 'Russian',
                'name' => 'Кулиев',
                'translator' => 'Elmir Kuliev',
                'filename' => 'https://tanzil.net/trans/ru.kuliev',
            ),
            80 => 
            array (
                'language' => 'Russian',
                'name' => 'Кулиев + ас-Саади',
            'translator' => 'Elmir Kuliev (with Abd ar-Rahman as-Saadi\'s commentaries)',
                'filename' => 'https://tanzil.net/trans/ru.kuliev-alsaadi',
            ),
            81 => 
            array (
                'language' => 'Russian',
                'name' => 'Османов',
                'translator' => 'Magomed-Nuri Osmanovich Osmanov',
                'filename' => 'https://tanzil.net/trans/ru.osmanov',
            ),
            82 => 
            array (
                'language' => 'Russian',
                'name' => 'Порохова',
                'translator' => 'V. Porokhova',
                'filename' => 'https://tanzil.net/trans/ru.porokhova',
            ),
            83 => 
            array (
                'language' => 'Russian',
                'name' => 'Саблуков',
                'translator' => 'Gordy Semyonovich Sablukov',
                'filename' => 'https://tanzil.net/trans/ru.sablukov',
            ),
            84 => 
            array (
                'language' => 'Sindhi',
                'name' => 'امروٽي',
                'translator' => 'Taj Mehmood Amroti',
                'filename' => 'https://tanzil.net/trans/sd.amroti',
            ),
            85 => 
            array (
                'language' => 'Somali',
                'name' => 'Abduh',
                'translator' => 'Mahmud Muhammad Abduh',
                'filename' => 'https://tanzil.net/trans/so.abduh',
            ),
            86 => 
            array (
                'language' => 'Spanish',
                'name' => 'Bornez',
                'translator' => 'Raúl González Bórnez *',
                'filename' => 'https://tanzil.net/trans/es.bornez',
            ),
            87 => 
            array (
                'language' => 'Spanish',
                'name' => 'Cortes',
                'translator' => 'Julio Cortes',
                'filename' => 'https://tanzil.net/trans/es.cortes',
            ),
            88 => 
            array (
                'language' => 'Spanish',
                'name' => 'Garcia',
                'translator' => 'Muhammad Isa García *',
                'filename' => 'https://tanzil.net/trans/es.garcia',
            ),
            89 => 
            array (
                'language' => 'Swahili',
                'name' => 'Al-Barwani',
                'translator' => 'Ali Muhsin Al-Barwani',
                'filename' => 'https://tanzil.net/trans/sw.barwani',
            ),
            90 => 
            array (
                'language' => 'Swedish',
                'name' => 'Bernström',
                'translator' => 'Knut Bernström',
                'filename' => 'https://tanzil.net/trans/sv.bernstrom',
            ),
            91 => 
            array (
                'language' => 'Tajik',
                'name' => 'Оятӣ',
                'translator' => 'AbdolMohammad Ayati',
                'filename' => 'https://tanzil.net/trans/tg.ayati',
            ),
            92 => 
            array (
                'language' => 'Tamil',
                'name' => 'ஜான் டிரஸ்ட்',
                'translator' => 'Jan Turst Foundation',
                'filename' => 'https://tanzil.net/trans/ta.tamil',
            ),
            93 => 
            array (
                'language' => 'Tatar',
                'name' => 'Yakub Ibn Nugman',
                'translator' => 'Yakub Ibn Nugman',
                'filename' => 'https://tanzil.net/trans/tt.nugman',
            ),
            94 => 
            array (
                'language' => 'Thai',
                'name' => 'ภาษาไทย',
                'translator' => 'King Fahad Quran Complex',
                'filename' => 'https://tanzil.net/trans/th.thai',
            ),
            95 => 
            array (
                'language' => 'Turkish',
                'name' => 'Abdulbakî Gölpınarlı',
                'translator' => 'Abdulbaki Golpinarli',
                'filename' => 'https://tanzil.net/trans/tr.golpinarli',
            ),
            96 => 
            array (
                'language' => 'Turkish',
                'name' => 'Alİ Bulaç',
                'translator' => 'Alİ Bulaç',
                'filename' => 'https://tanzil.net/trans/tr.bulac',
            ),
            97 => 
            array (
                'language' => 'Turkish',
                'name' => 'Çeviriyazı',
                'translator' => 'Muhammet Abay',
                'filename' => 'https://tanzil.net/trans/tr.transliteration',
            ),
            98 => 
            array (
                'language' => 'Turkish',
                'name' => 'Diyanet İşleri',
                'translator' => 'Diyanet Isleri',
                'filename' => 'https://tanzil.net/trans/tr.diyanet',
            ),
            99 => 
            array (
                'language' => 'Turkish',
                'name' => 'Diyanet Vakfı',
                'translator' => 'Diyanet Vakfi',
                'filename' => 'https://tanzil.net/trans/tr.vakfi',
            ),
            100 => 
            array (
                'language' => 'Turkish',
                'name' => 'Edip Yüksel',
                'translator' => 'Edip Yüksel',
                'filename' => 'https://tanzil.net/trans/tr.yuksel',
            ),
            101 => 
            array (
                'language' => 'Turkish',
                'name' => 'Elmalılı Hamdi Yazır',
                'translator' => 'Elmalili Hamdi Yazir',
                'filename' => 'https://tanzil.net/trans/tr.yazir',
            ),
            102 => 
            array (
                'language' => 'Turkish',
                'name' => 'Öztürk',
                'translator' => 'Yasar Nuri Ozturk *',
                'filename' => 'https://tanzil.net/trans/tr.ozturk',
            ),
            103 => 
            array (
                'language' => 'Turkish',
                'name' => 'Suat Yıldırım',
                'translator' => 'Suat Yildirim',
                'filename' => 'https://tanzil.net/trans/tr.yildirim',
            ),
            104 => 
            array (
                'language' => 'Turkish',
                'name' => 'Süleyman Ateş',
                'translator' => 'Suleyman Ates',
                'filename' => 'https://tanzil.net/trans/tr.ates',
            ),
            105 => 
            array (
                'language' => 'Urdu',
                'name' => 'ابوالاعلی مودودی',
                'translator' => 'Abul A\'ala Maududi *',
                'filename' => 'https://tanzil.net/trans/ur.maududi',
            ),
            106 => 
            array (
                'language' => 'Urdu',
                'name' => 'احمد رضا خان',
                'translator' => 'Ahmed Raza Khan *',
                'filename' => 'https://tanzil.net/trans/ur.kanzuliman',
            ),
            107 => 
            array (
                'language' => 'Urdu',
                'name' => 'احمد علی',
                'translator' => 'Ahmed Ali *',
                'filename' => 'https://tanzil.net/trans/ur.ahmedali',
            ),
            108 => 
            array (
                'language' => 'Urdu',
                'name' => 'جالندہری',
                'translator' => 'Fateh Muhammad Jalandhry',
                'filename' => 'https://tanzil.net/trans/ur.jalandhry',
            ),
            109 => 
            array (
                'language' => 'Urdu',
                'name' => 'طاہر القادری',
                'translator' => 'Tahir ul Qadri *',
                'filename' => 'https://tanzil.net/trans/ur.qadri',
            ),
            110 => 
            array (
                'language' => 'Urdu',
                'name' => 'علامہ جوادی',
                'translator' => 'Syed Zeeshan Haider Jawadi',
                'filename' => 'https://tanzil.net/trans/ur.jawadi',
            ),
            111 => 
            array (
                'language' => 'Urdu',
                'name' => 'محمد جوناگڑھی',
                'translator' => 'Muhammad Junagarhi *',
                'filename' => 'https://tanzil.net/trans/ur.junagarhi',
            ),
            112 => 
            array (
                'language' => 'Urdu',
                'name' => 'محمد حسین نجفی',
                'translator' => 'Muhammad Hussain Najafi  *',
                'filename' => 'https://tanzil.net/trans/ur.najafi',
            ),
            113 => 
            array (
                'language' => 'Uyghur',
                'name' => 'محمد صالح',
                'translator' => 'Muhammad Saleh',
                'filename' => 'https://tanzil.net/trans/ug.saleh',
            ),
            114 => 
            array (
                'language' => 'Uzbek',
                'name' => 'Мухаммад Содик',
                'translator' => 'Muhammad Sodik Muhammad Yusuf',
                'filename' => 'https://tanzil.net/trans/uz.sodik',
            ),
        ));
        
        
    }
}