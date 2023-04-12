/*
 Navicat Premium Data Transfer

 Source Server         : local
 Source Server Type    : MySQL
 Source Server Version : 80023
 Source Host           : localhost:3307
 Source Schema         : berimbasket_bot_generator_promoter

 Target Server Type    : MySQL
 Target Server Version : 80023
 File Encoding         : 65001

 Date: 07/04/2023 06:21:40
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for quran_surahs
-- ----------------------------
DROP TABLE IF EXISTS `quran_surahs`;
CREATE TABLE `quran_surahs`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `arabic` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `latin` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `english` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sajda` varchar(55) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ayah` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of quran_surahs
-- ----------------------------
INSERT INTO `quran_surahs` VALUES (1, 'سورة الفاتحة', 'Al-Fatiha', 'The Opening', '1', '0', 7, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (2, 'سورة البقرة', 'Al-Baqara', 'The Cow', '2', '0', 286, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (3, 'سورة آل عمران', 'Aal-e-Imran', 'The family of Imran', '2', '0', 200, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (4, 'سورة النساء', 'An-Nisa', 'The Women', '2', '0', 176, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (5, 'سورة المائدة', 'Al-Maeda', 'The Table Spread', '2', '0', 120, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (6, 'سورة الأنعام', 'Al-Anaam', 'The cattle', '1', '0', 165, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (7, 'سورة الأعراف', 'Al-Araf', 'The heights', '1', '206', 206, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (8, 'سورة الأنفال', 'Al-Anfal', 'Spoils of war, booty', '2', '0', 75, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (9, 'سورة التوبة', 'At-Taubah', 'Repentance', '2', '0', 129, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (10, 'سورة يونس', 'Yunus', 'Jonah', '1', '0', 109, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (11, 'سورة هود', 'Hud', 'Hud', '1', '0', 123, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (12, 'سورة يوسف', 'Yusuf', 'Joseph', '1', '0', 111, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (13, 'سورة الرعد', 'Ar-Rad', 'The Thunder', '1', '15', 43, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (14, 'سورة إبراهيم', 'Ibrahim', 'Abraham', '1', '0', 52, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (15, 'سورة الحجر', 'Al-Hijr', 'Stoneland, Rock city, Al-Hijr valley', '1', '0', 99, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (16, 'سورة النحل', 'An-Nahl', 'The Bee', '1', '50', 128, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (17, 'سورة الإسراء', 'Al-Isra', 'The night journey', '1', '100', 111, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (18, 'سورة الكهف', 'Al-Kahf', 'The cave', '1', '0', 110, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (19, 'سورة مريم', 'Maryam', 'Mary', '1', '58', 98, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (20, 'سورة طه', 'Taha', 'Taha', '1', '0', 135, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (21, 'سورة الأنبياء', 'Al-Anbiya', 'The Prophets', '1', '0', 112, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (22, 'سورة الحج', 'Al-Hajj', 'The Pilgrimage', '1', '18', 78, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (23, 'سورة المؤمنون', 'Al-Mumenoon', 'The Believers', '1', '0', 118, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (24, 'سورة النور', 'An-Noor', 'The Light', '1', '0', 64, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (25, 'سورة الفرقان', 'Al-Furqan', 'The Standard', '1', '60', 77, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (26, 'سورة الشعراء', 'Ash-Shuara', 'The Poets', '1', '0', 227, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (27, 'سورة النمل', 'An-Naml', 'THE ANT', '1', '26', 93, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (28, 'سورة القصص', 'Al-Qasas', 'The Story', '1', '0', 88, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (29, 'سورة العنكبوت', 'Al-Ankaboot', 'The Spider', '1', '0', 69, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (30, 'سورة الروم', 'Ar-Room', 'The Romans', '1', '0', 60, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (31, 'سورة لقمان', 'Luqman', 'Luqman', '1', '0', 34, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (32, 'سورة السجدة', 'As-Sajda', 'The Prostration', '1', '15', 30, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (33, 'سورة الأحزاب', 'Al-Ahzab', 'The Coalition', '1', '0', 73, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (34, 'سورة سبأ', 'Saba', 'Saba', '1', '0', 54, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (35, 'سورة فاطر', 'Fatir', 'Originator', '1', '0', 45, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (36, 'سورة يس', 'Ya Seen', 'Ya Seen', '1', '0', 83, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (37, 'سورة الصافات', 'As-Saaffat', 'Those who set the ranks', '1', '0', 182, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (38, 'سورة ص', 'Sad', 'Sad', '1', '24', 88, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (39, 'سورة الزمر', 'Az-Zumar', 'The Troops', '1', '0', 75, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (40, 'سورة غافر', 'Ghafir', 'The Forgiver', '1', '0', 85, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (41, 'سورة فصلت', 'Fussilat', 'Explained in detail', '1', '38', 54, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (42, 'سورة الشورى', 'Ash-Shura', 'Council, Consultation', '1', '0', 53, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (43, 'سورة الزخرف', 'Az-Zukhruf', 'Ornaments of Gold', '1', '0', 89, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (44, 'سورة الدخان', 'Ad-Dukhan', 'The Smoke', '1', '0', 59, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (45, 'سورة الجاثية', 'Al-Jathiya', 'Crouching', '1', '0', 37, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (46, 'سورة الأحقاف', 'Al-Ahqaf', 'The wind-curved sandhills', '1', '0', 35, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (47, 'سورة محمد', 'Muhammad', 'Muhammad', '2', '0', 38, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (48, 'سورة الفتح', 'Al-Fath', 'The victory', '2', '0', 29, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (49, 'سورة الحجرات', 'Al-Hujraat', 'The private apartments', '2', '0', 18, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (50, 'سورة ق', 'Qaf', 'Qaf', '1', '0', 45, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (51, 'سورة الذاريات', 'Adh-Dhariyat', 'The winnowing winds', '1', '0', 60, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (52, 'سورة الطور', 'At-tur', 'Mount Sinai', '1', '0', 49, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (53, 'سورة النجم', 'An-Najm', 'The Star', '1', '62', 62, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (54, 'سورة القمر', 'Al-Qamar', 'The moon', '1', '0', 55, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (55, 'سورة الرحمن', 'Al-Rahman', 'The Beneficient', '1', '0', 78, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (56, 'سورة الواقعة', 'Al-Waqia', 'The Event, The Inevitable', '1', '0', 96, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (57, 'سورة الحديد', 'Al-Hadid', 'The Iron', '2', '0', 29, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (58, 'سورة المجادلة', 'Al-Mujadila', 'She that disputes', '2', '0', 22, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (59, 'سورة الحشر', 'Al-Hashr', 'Exile', '2', '0', 24, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (60, 'سورة الممتحنة', 'Al-Mumtahina', 'She that is to be examined', '2', '0', 13, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (61, 'سورة الصف', 'As-Saff', 'The Ranks', '2', '0', 14, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (62, 'سورة الجمعة', 'Al-Jumua', 'The congregation, Friday', '2', '0', 11, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (63, 'سورة المنافقون', 'Al-Munafiqoon', 'The Hypocrites', '2', '0', 11, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (64, 'سورة التغابن', 'At-Taghabun', 'Mutual Disillusion', '2', '0', 18, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (65, 'سورة الطلاق', 'At-Talaq', 'Divorce', '2', '0', 12, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (66, 'سورة التحريم', 'At-Tahrim', 'Banning', '2', '0', 12, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (67, 'سورة الملك', 'Al-Mulk', 'The Sovereignty', '1', '0', 30, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (68, 'سورة القلم', 'Al-Qalam', 'The Pen', '1', '0', 52, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (69, 'سورة الحاقة', 'Al-Haaqqa', 'The reality', '1', '0', 52, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (70, 'سورة المعارج', 'Al-Maarij', 'The Ascending stairways', '1', '0', 44, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (71, 'سورة نوح', 'Nooh', 'Nooh', '1', '0', 28, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (72, 'سورة الجن', 'Al-Jinn', 'The Jinn', '1', '0', 28, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (73, 'سورة المزمل', 'Al-Muzzammil', 'The enshrouded one', '1', '0', 20, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (74, 'سورة المدثر', 'Al-Muddathir', 'The cloaked one', '1', '0', 56, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (75, 'سورة القيامة', 'Al-Qiyama', 'The rising of the dead', '1', '0', 40, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (76, 'سورة الإنسان', 'Al-Insan', 'The man', '2', '0', 31, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (77, 'سورة المرسلات', 'Al-Mursalat', 'The emissaries', '1', '0', 50, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (78, 'سورة النبأ', 'An-Naba', 'The tidings', '1', '0', 40, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (79, 'سورة النازعات', 'An-Naziat', 'Those who drag forth', '1', '0', 46, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (80, 'سورة عبس', 'Abasa', 'He Frowned', '1', '0', 42, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (81, 'سورة التكوير', 'At-Takwir', 'The Overthrowing', '1', '0', 29, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (82, 'سورة الإنفطار', 'AL-Infitar', 'The Cleaving', '1', '0', 19, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (83, 'سورة المطففين', 'Al-Mutaffifin', 'Defrauding', '1', '0', 36, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (84, 'سورة الانشقاق', 'Al-Inshiqaq', 'The Sundering, Splitting Open', '1', '21', 25, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (85, 'سورة البروج', 'Al-Burooj', 'The Mansions of the stars', '1', '0', 22, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (86, 'سورة الطارق', 'At-Tariq', 'The morning star', '1', '0', 17, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (87, 'سورة الأعلى', 'Al-Ala', 'The Most High', '1', '0', 19, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (88, 'سورة الغاشية', 'Al-Ghashiya', 'The Overwhelming', '1', '0', 26, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (89, 'سورة الفجر', 'Al-Fajr', 'The Dawn', '1', '0', 30, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (90, 'سورة البلد', 'Al-Balad', 'The City', '1', '0', 20, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (91, 'سورة الشمس', 'Ash-Shams', 'The Sun', '1', '0', 15, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (92, 'سورة الليل', 'Al-Lail', 'The night', '1', '0', 21, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (93, 'سورة الضحى', 'Ad-Dhuha', 'The morning hours', '1', '0', 11, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (94, 'سورة الشرح', 'Al-Inshirah', 'Solace', '1', '0', 8, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (95, 'سورة التين', 'At-Tin', 'The Fig', '1', '0', 8, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (96, 'سورة العلق', 'Al-Alaq', 'The Clot', '1', '19', 19, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (97, 'سورة القدر', 'Al-Qadr', 'The Power', '1', '0', 5, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (98, 'سورة البينة', 'Al-Bayyina', 'The Clear proof', '2', '0', 8, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (99, 'سورة الزلزلة', 'Al-Zalzala', 'The earthquake', '2', '0', 8, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (100, 'سورة العاديات', 'Al-Adiyat', 'The Chargers', '1', '0', 11, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (101, 'سورة القارعة', 'Al-Qaria', 'The Calamity', '1', '0', 11, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (102, 'سورة التكاثر', 'At-Takathur', 'Competition', '1', '0', 8, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (103, 'سورة العصر', 'Al-Asr', 'The declining day', '1', '0', 3, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (104, 'سورة الهمزة', 'Al-Humaza', 'The Traducer', '1', '0', 9, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (105, 'سورة الفيل', 'Al-fil', 'The Elephant', '1', '0', 5, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (106, 'سورة قريش', 'Quraish', 'Quraish', '1', '0', 4, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (107, 'سورة الماعون', 'Al-Maun', 'Alms Giving', '1', '0', 7, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (108, 'سورة الكوثر', 'Al-Kauther', 'Abundance', '1', '0', 3, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (109, 'سورة الكافرون', 'Al-Kafiroon', 'The Disbelievers', '1', '0', 6, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (110, 'سورة النصر', 'An-Nasr', 'The Succour', '2', '0', 3, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (111, 'سورة المسد', 'Al-Masadd', 'The Flame', '1', '0', 5, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (112, 'سورة الإخلاص', 'Al-Ikhlas', 'Absoluteness', '1', '0', 4, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (113, 'سورة الفلق', 'Al-Falaq', 'The day break', '1', '0', 5, NULL, NULL);
INSERT INTO `quran_surahs` VALUES (114, 'سورة الناس', 'An-Nas', 'The mankind', '1', '0', 6, NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;
