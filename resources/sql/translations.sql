/*
 Navicat Premium Data Transfer

 Source Server         : localhost
 Source Server Type    : MySQL
 Source Server Version : 100428 (10.4.28-MariaDB)
 Source Host           : localhost:3306
 Source Schema         : berimbasket_bot_generator_promoter

 Target Server Type    : MySQL
 Target Server Version : 100428 (10.4.28-MariaDB)
 File Encoding         : 65001

 Date: 16/04/2023 17:09:15
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for translations
-- ----------------------------
DROP TABLE IF EXISTS `translations`;
CREATE TABLE `translations`  (
  `language` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `translator` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of translations
-- ----------------------------
INSERT INTO `translations` VALUES ('Albanian', 'Efendi Nahi', 'Hasan Efendi Nahi', 'https://tanzil.net/trans/sq.nahi');
INSERT INTO `translations` VALUES ('Albanian', 'Feti Mehdiu', 'Feti Mehdiu', 'https://tanzil.net/trans/sq.mehdiu');
INSERT INTO `translations` VALUES ('Albanian', 'Sherif Ahmeti', 'Sherif Ahmeti', 'https://tanzil.net/trans/sq.ahmeti');
INSERT INTO `translations` VALUES ('Amazigh', 'At Mensur', 'Ramdane At Mansour *', 'https://tanzil.net/trans/ber.mensur');
INSERT INTO `translations` VALUES ('Arabic', 'تفسير الجلالين', 'Jalal ad-Din al-Mahalli and Jalal ad-Din as-Suyuti *', 'https://tanzil.net/trans/ar.jalalayn');
INSERT INTO `translations` VALUES ('Arabic', 'تفسير المیسر', 'King Fahad Quran Complex *', 'https://tanzil.net/trans/ar.muyassar');
INSERT INTO `translations` VALUES ('Amharic', 'ሳዲቅ & ሳኒ ሐቢብ', 'Muhammed Sadiq and Muhammed Sani Habib *', 'https://tanzil.net/trans/am.sadiq');
INSERT INTO `translations` VALUES ('Azerbaijani', 'Məmmədəliyev & Bünyadov', 'Vasim Mammadaliyev and Ziya Bunyadov * †', 'https://tanzil.net/trans/az.mammadaliyev');
INSERT INTO `translations` VALUES ('Azerbaijani', 'Musayev', 'Alikhan Musayev', 'https://tanzil.net/trans/az.musayev');
INSERT INTO `translations` VALUES ('Bengali', 'জহুরুল হক', 'Zohurul Hoque *', 'https://tanzil.net/trans/bn.hoque');
INSERT INTO `translations` VALUES ('Bengali', 'মুহিউদ্দীন খান', 'Muhiuddin Khan', 'https://tanzil.net/trans/bn.bengali');
INSERT INTO `translations` VALUES ('Bosnian', 'Korkut', 'Besim Korkut *', 'https://tanzil.net/trans/bs.korkut');
INSERT INTO `translations` VALUES ('Bosnian', 'Mlivo', 'Mustafa Mlivo', 'https://tanzil.net/trans/bs.mlivo');
INSERT INTO `translations` VALUES ('Bulgarian', 'Теофанов', 'Tzvetan Theophanov', 'https://tanzil.net/trans/bg.theophanov');
INSERT INTO `translations` VALUES ('Chinese', 'Ma Jian', 'Ma Jian', 'https://tanzil.net/trans/zh.jian');
INSERT INTO `translations` VALUES ('Chinese', 'Ma Jian (Traditional)', 'Ma Jian', 'https://tanzil.net/trans/zh.majian');
INSERT INTO `translations` VALUES ('Czech', 'Hrbek', 'Preklad I. Hrbek', 'https://tanzil.net/trans/cs.hrbek');
INSERT INTO `translations` VALUES ('Czech', 'Nykl', 'A. R. Nykl', 'https://tanzil.net/trans/cs.nykl');
INSERT INTO `translations` VALUES ('Divehi', 'ދިވެހި', 'Office of the President of Maldives', 'https://tanzil.net/trans/dv.divehi');
INSERT INTO `translations` VALUES ('Dutch', 'Keyzer', 'Salomo Keyzer', 'https://tanzil.net/trans/nl.keyzer');
INSERT INTO `translations` VALUES ('Dutch', 'Leemhuis', 'Fred Leemhuis', 'https://tanzil.net/trans/nl.leemhuis');
INSERT INTO `translations` VALUES ('Dutch', 'Siregar', 'Sofian S. Siregar', 'https://tanzil.net/trans/nl.siregar');
INSERT INTO `translations` VALUES ('English', 'Ahmed Ali', 'Ahmed Ali *', 'https://tanzil.net/trans/en.ahmedali');
INSERT INTO `translations` VALUES ('English', 'Ahmed Raza Khan', 'Ahmed Raza Khan *', 'https://tanzil.net/trans/en.ahmedraza');
INSERT INTO `translations` VALUES ('English', 'Arberry', 'A. J. Arberry *', 'https://tanzil.net/trans/en.arberry');
INSERT INTO `translations` VALUES ('English', 'Daryabadi', 'Abdul Majid Daryabadi *', 'https://tanzil.net/trans/en.daryabadi');
INSERT INTO `translations` VALUES ('English', 'Hilali & Khan', 'Muhammad Taqi-ud-Din al-Hilali and Muhammad Muhsin Khan *', 'https://tanzil.net/trans/en.hilali');
INSERT INTO `translations` VALUES ('English', 'Itani', 'Talal Itani', 'https://tanzil.net/trans/en.itani');
INSERT INTO `translations` VALUES ('English', 'Maududi', 'Abul Ala Maududi *', 'https://tanzil.net/trans/en.maududi');
INSERT INTO `translations` VALUES ('English', 'Mubarakpuri', 'Safi-ur-Rahman al-Mubarakpuri *', 'https://tanzil.net/trans/en.mubarakpuri');
INSERT INTO `translations` VALUES ('English', 'Pickthall', 'Mohammed Marmaduke William Pickthall *', 'https://tanzil.net/trans/en.pickthall');
INSERT INTO `translations` VALUES ('English', 'Qarai', 'Ali Quli Qarai', 'https://tanzil.net/trans/en.qarai');
INSERT INTO `translations` VALUES ('English', 'Qaribullah & Darwish', 'Hasan al-Fatih Qaribullah and Ahmad Darwish', 'https://tanzil.net/trans/en.qaribullah');
INSERT INTO `translations` VALUES ('English', 'Saheeh International', 'Saheeh International *', 'https://tanzil.net/trans/en.sahih');
INSERT INTO `translations` VALUES ('English', 'Sarwar', 'Muhammad Sarwar *', 'https://tanzil.net/trans/en.sarwar');
INSERT INTO `translations` VALUES ('English', 'Shakir', 'Mohammad Habib Shakir *', 'https://tanzil.net/trans/en.shakir');
INSERT INTO `translations` VALUES ('English', 'Transliteration', 'English Transliteration', 'https://tanzil.net/trans/en.transliteration');
INSERT INTO `translations` VALUES ('English', 'Wahiduddin Khan', 'Wahiduddin Khan *', 'https://tanzil.net/trans/en.wahiduddin');
INSERT INTO `translations` VALUES ('English', 'Yusuf Ali', 'Abdullah Yusuf Ali *', 'https://tanzil.net/trans/en.yusufali');
INSERT INTO `translations` VALUES ('French', 'Hamidullah', 'Muhammad Hamidullah *', 'https://tanzil.net/trans/fr.hamidullah');
INSERT INTO `translations` VALUES ('German', 'Abu Rida', 'Abu Rida Muhammad ibn Ahmad ibn Rassoul', 'https://tanzil.net/trans/de.aburida');
INSERT INTO `translations` VALUES ('German', 'Bubenheim & Elyas', 'A. S. F. Bubenheim and N. Elyas *', 'https://tanzil.net/trans/de.bubenheim');
INSERT INTO `translations` VALUES ('German', 'Khoury', 'Adel Theodor Khoury *', 'https://tanzil.net/trans/de.khoury');
INSERT INTO `translations` VALUES ('German', 'Zaidan', 'Amir Zaidan', 'https://tanzil.net/trans/de.zaidan');
INSERT INTO `translations` VALUES ('Hausa', 'Gumi', 'Abubakar Mahmoud Gumi', 'https://tanzil.net/trans/ha.gumi');
INSERT INTO `translations` VALUES ('Hindi', 'फ़ारूक़ ख़ान & अहमद', 'Muhammad Farooq Khan and Muhammad Ahmed', 'https://tanzil.net/trans/hi.farooq');
INSERT INTO `translations` VALUES ('Hindi', 'फ़ारूक़ ख़ान & नदवी', 'Suhel Farooq Khan and Saifur Rahman Nadwi', 'https://tanzil.net/trans/hi.hindi');
INSERT INTO `translations` VALUES ('Indonesian', 'Bahasa Indonesia', 'Indonesian Ministry of Religious Affairs', 'https://tanzil.net/trans/id.indonesian');
INSERT INTO `translations` VALUES ('Indonesian', 'Quraish Shihab', 'Muhammad Quraish Shihab et al. *', 'https://tanzil.net/trans/id.muntakhab');
INSERT INTO `translations` VALUES ('Indonesian', 'Tafsir Jalalayn', 'Jalal ad-Din al-Mahalli and Jalal ad-Din as-Suyuti *', 'https://tanzil.net/trans/id.jalalayn');
INSERT INTO `translations` VALUES ('Italian', 'Piccardo', 'Hamza Roberto Piccardo *', 'https://tanzil.net/trans/it.piccardo');
INSERT INTO `translations` VALUES ('Japanese', 'Japanese', 'Unknown', 'https://tanzil.net/trans/ja.japanese');
INSERT INTO `translations` VALUES ('Korean', 'Korean', 'Unknown', 'https://tanzil.net/trans/ko.korean');
INSERT INTO `translations` VALUES ('Kurdish', 'ته‌فسیری ئاسان', 'Burhan Muhammad-Amin', 'https://tanzil.net/trans/ku.asan');
INSERT INTO `translations` VALUES ('Malay', 'Basmeih', 'Abdullah Muhammad Basmeih', 'https://tanzil.net/trans/ms.basmeih');
INSERT INTO `translations` VALUES ('Malayalam', 'അബ്ദുല്‍ ഹമീദ് & പറപ്പൂര്‍', 'Cheriyamundam Abdul Hameed and Kunhi Mohammed Parappoor', 'https://tanzil.net/trans/ml.abdulhameed');
INSERT INTO `translations` VALUES ('Malayalam', 'കാരകുന്ന് & എളയാവൂര്', 'Muhammad Karakunnu and Vanidas Elayavoor *', 'https://tanzil.net/trans/ml.karakunnu');
INSERT INTO `translations` VALUES ('Norwegian', 'Einar Berg', 'Einar Berg', 'https://tanzil.net/trans/no.berg');
INSERT INTO `translations` VALUES ('Pashto', 'عبدالولي', 'Abdulwali Khan', 'https://tanzil.net/trans/ps.abdulwali');
INSERT INTO `translations` VALUES ('Persian', 'انصاریان', 'Hussain Ansarian *', 'https://tanzil.net/trans/fa.ansarian');
INSERT INTO `translations` VALUES ('Persian', 'آیتی', 'AbdolMohammad Ayati *', 'https://tanzil.net/trans/fa.ayati');
INSERT INTO `translations` VALUES ('Persian', 'بهرام‌پور', 'Abolfazl Bahrampour *', 'https://tanzil.net/trans/fa.bahrampour');
INSERT INTO `translations` VALUES ('Persian', 'قرائتی', 'Mohsen Gharaati *', 'https://tanzil.net/trans/fa.gharaati');
INSERT INTO `translations` VALUES ('Persian', 'الهی قمشه‌ای', 'Mahdi Elahi Ghomshei *', 'https://tanzil.net/trans/fa.ghomshei');
INSERT INTO `translations` VALUES ('Persian', 'خرمدل', 'Mostafa Khorramdel *', 'https://tanzil.net/trans/fa.khorramdel');
INSERT INTO `translations` VALUES ('Persian', 'خرمشاهی', 'Baha\'oddin Khorramshahi *', 'https://tanzil.net/trans/fa.khorramshahi');
INSERT INTO `translations` VALUES ('Persian', 'صادقی تهرانی', 'Mohammad Sadeqi Tehrani *', 'https://tanzil.net/trans/fa.sadeqi');
INSERT INTO `translations` VALUES ('Persian', 'صفوی', 'Sayyed Mohammad Reza Safavi *', 'https://tanzil.net/trans/fa.safavi');
INSERT INTO `translations` VALUES ('Persian', 'فولادوند', 'Mohammad Mahdi Fooladvand *', 'https://tanzil.net/trans/fa.fooladvand');
INSERT INTO `translations` VALUES ('Persian', 'مجتبوی', 'Sayyed Jalaloddin Mojtabavi *', 'https://tanzil.net/trans/fa.mojtabavi');
INSERT INTO `translations` VALUES ('Persian', 'معزی', 'Mohammad Kazem Moezzi', 'https://tanzil.net/trans/fa.moezzi');
INSERT INTO `translations` VALUES ('Persian', 'مکارم شیرازی', 'Naser Makarem Shirazi *', 'https://tanzil.net/trans/fa.makarem');
INSERT INTO `translations` VALUES ('Polish', 'Bielawskiego', 'Józefa Bielawskiego', 'https://tanzil.net/trans/pl.bielawskiego');
INSERT INTO `translations` VALUES ('Portuguese', 'El-Hayek', 'Samir El-Hayek', 'https://tanzil.net/trans/pt.elhayek');
INSERT INTO `translations` VALUES ('Romanian', 'Grigore', 'George Grigore', 'https://tanzil.net/trans/ro.grigore');
INSERT INTO `translations` VALUES ('Russian', 'Абу Адель', 'Abu Adel', 'https://tanzil.net/trans/ru.abuadel');
INSERT INTO `translations` VALUES ('Russian', 'Аль-Мунтахаб', 'Ministry of Awqaf, Egypt', 'https://tanzil.net/trans/ru.muntahab');
INSERT INTO `translations` VALUES ('Russian', 'Калям Шариф', 'Muslim Religious Board of the Republiс of Tatarstan *', 'https://tanzil.net/trans/ru.kalam');
INSERT INTO `translations` VALUES ('Russian', 'Крачковский', 'Ignaty Yulianovich Krachkovsky *', 'https://tanzil.net/trans/ru.krachkovsky');
INSERT INTO `translations` VALUES ('Russian', 'Кулиев', 'Elmir Kuliev', 'https://tanzil.net/trans/ru.kuliev');
INSERT INTO `translations` VALUES ('Russian', 'Кулиев + ас-Саади', 'Elmir Kuliev (with Abd ar-Rahman as-Saadi\'s commentaries)', 'https://tanzil.net/trans/ru.kuliev-alsaadi');
INSERT INTO `translations` VALUES ('Russian', 'Османов', 'Magomed-Nuri Osmanovich Osmanov', 'https://tanzil.net/trans/ru.osmanov');
INSERT INTO `translations` VALUES ('Russian', 'Порохова', 'V. Porokhova', 'https://tanzil.net/trans/ru.porokhova');
INSERT INTO `translations` VALUES ('Russian', 'Саблуков', 'Gordy Semyonovich Sablukov', 'https://tanzil.net/trans/ru.sablukov');
INSERT INTO `translations` VALUES ('Sindhi', 'امروٽي', 'Taj Mehmood Amroti', 'https://tanzil.net/trans/sd.amroti');
INSERT INTO `translations` VALUES ('Somali', 'Abduh', 'Mahmud Muhammad Abduh', 'https://tanzil.net/trans/so.abduh');
INSERT INTO `translations` VALUES ('Spanish', 'Bornez', 'Raúl González Bórnez *', 'https://tanzil.net/trans/es.bornez');
INSERT INTO `translations` VALUES ('Spanish', 'Cortes', 'Julio Cortes', 'https://tanzil.net/trans/es.cortes');
INSERT INTO `translations` VALUES ('Spanish', 'Garcia', 'Muhammad Isa García *', 'https://tanzil.net/trans/es.garcia');
INSERT INTO `translations` VALUES ('Swahili', 'Al-Barwani', 'Ali Muhsin Al-Barwani', 'https://tanzil.net/trans/sw.barwani');
INSERT INTO `translations` VALUES ('Swedish', 'Bernström', 'Knut Bernström', 'https://tanzil.net/trans/sv.bernstrom');
INSERT INTO `translations` VALUES ('Tajik', 'Оятӣ', 'AbdolMohammad Ayati', 'https://tanzil.net/trans/tg.ayati');
INSERT INTO `translations` VALUES ('Tamil', 'ஜான் டிரஸ்ட்', 'Jan Turst Foundation', 'https://tanzil.net/trans/ta.tamil');
INSERT INTO `translations` VALUES ('Tatar', 'Yakub Ibn Nugman', 'Yakub Ibn Nugman', 'https://tanzil.net/trans/tt.nugman');
INSERT INTO `translations` VALUES ('Thai', 'ภาษาไทย', 'King Fahad Quran Complex', 'https://tanzil.net/trans/th.thai');
INSERT INTO `translations` VALUES ('Turkish', 'Abdulbakî Gölpınarlı', 'Abdulbaki Golpinarli', 'https://tanzil.net/trans/tr.golpinarli');
INSERT INTO `translations` VALUES ('Turkish', 'Alİ Bulaç', 'Alİ Bulaç', 'https://tanzil.net/trans/tr.bulac');
INSERT INTO `translations` VALUES ('Turkish', 'Çeviriyazı', 'Muhammet Abay', 'https://tanzil.net/trans/tr.transliteration');
INSERT INTO `translations` VALUES ('Turkish', 'Diyanet İşleri', 'Diyanet Isleri', 'https://tanzil.net/trans/tr.diyanet');
INSERT INTO `translations` VALUES ('Turkish', 'Diyanet Vakfı', 'Diyanet Vakfi', 'https://tanzil.net/trans/tr.vakfi');
INSERT INTO `translations` VALUES ('Turkish', 'Edip Yüksel', 'Edip Yüksel', 'https://tanzil.net/trans/tr.yuksel');
INSERT INTO `translations` VALUES ('Turkish', 'Elmalılı Hamdi Yazır', 'Elmalili Hamdi Yazir', 'https://tanzil.net/trans/tr.yazir');
INSERT INTO `translations` VALUES ('Turkish', 'Öztürk', 'Yasar Nuri Ozturk *', 'https://tanzil.net/trans/tr.ozturk');
INSERT INTO `translations` VALUES ('Turkish', 'Suat Yıldırım', 'Suat Yildirim', 'https://tanzil.net/trans/tr.yildirim');
INSERT INTO `translations` VALUES ('Turkish', 'Süleyman Ateş', 'Suleyman Ates', 'https://tanzil.net/trans/tr.ates');
INSERT INTO `translations` VALUES ('Urdu', 'ابوالاعلی مودودی', 'Abul A\'ala Maududi *', 'https://tanzil.net/trans/ur.maududi');
INSERT INTO `translations` VALUES ('Urdu', 'احمد رضا خان', 'Ahmed Raza Khan *', 'https://tanzil.net/trans/ur.kanzuliman');
INSERT INTO `translations` VALUES ('Urdu', 'احمد علی', 'Ahmed Ali *', 'https://tanzil.net/trans/ur.ahmedali');
INSERT INTO `translations` VALUES ('Urdu', 'جالندہری', 'Fateh Muhammad Jalandhry', 'https://tanzil.net/trans/ur.jalandhry');
INSERT INTO `translations` VALUES ('Urdu', 'طاہر القادری', 'Tahir ul Qadri *', 'https://tanzil.net/trans/ur.qadri');
INSERT INTO `translations` VALUES ('Urdu', 'علامہ جوادی', 'Syed Zeeshan Haider Jawadi', 'https://tanzil.net/trans/ur.jawadi');
INSERT INTO `translations` VALUES ('Urdu', 'محمد جوناگڑھی', 'Muhammad Junagarhi *', 'https://tanzil.net/trans/ur.junagarhi');
INSERT INTO `translations` VALUES ('Urdu', 'محمد حسین نجفی', 'Muhammad Hussain Najafi  *', 'https://tanzil.net/trans/ur.najafi');
INSERT INTO `translations` VALUES ('Uyghur', 'محمد صالح', 'Muhammad Saleh', 'https://tanzil.net/trans/ug.saleh');
INSERT INTO `translations` VALUES ('Uzbek', 'Мухаммад Содик', 'Muhammad Sodik Muhammad Yusuf', 'https://tanzil.net/trans/uz.sodik');

SET FOREIGN_KEY_CHECKS = 1;
