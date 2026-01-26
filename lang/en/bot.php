<?php
// این فایل بخشی از ترجمه‌های موجود است و فقط کلیدهای جدید را اضافه می‌کنیم
// برای حفظ ترجمه‌های قبلی، باید محتوای کامل فایل را خواند و تغییر داد
// اینجا فقط کلیدهای جدید Prayer Bot را اضافه می‌کنیم

return [
    // ... سایر ترجمه‌ها موجود است ...
    
    // Prayer Bot Translations
    'prayer_recorded' => 'Rakats recorded',
    'prayer_removed' => 'Rakats removed',
    'prayer_not_found' => 'Rakats not found',
    'prayer_type' => 'Prayer type',
    'record_id' => 'ID',
    'to_remove' => 'To remove',
    'check_record_id' => 'Check the ID',
    'error_recording_prayer' => 'Error recording rakats',
    'error_removing_prayer' => 'Error removing rakats',
    'error_getting_stats' => 'Error getting stats',
    'error_setting_estimate' => 'Error setting estimate',
    
    // Prayer names
    'fajr' => 'Fajr',
    'dhuhr' => 'Dhuhr',
    'asr' => 'Asr',
    'maghrib' => 'Maghrib',
    'isha' => 'Isha',
    
    // Stats
    'weekly_stats' => 'Weekly Stats',
    'total_rakats' => 'Total Rakats',
    'total_records' => 'Total Records',
    'last_week' => 'Last Week',
    'rakats' => 'Rakats',
    'records' => 'Records',
    'by_prayer_type' => 'By Prayer Type',
    
    // Progress
    'progress' => 'Progress',
    'goal' => 'Goal',
    'completed' => 'Completed',
    'remaining' => 'Remaining',
    'percentage' => 'Percentage',
    'almost_done' => 'Almost done! Great progress!',
    'great_progress' => 'Great progress! Keep going!',
    'keep_going' => 'Keep going!',
    'good_start' => 'Good start! Continue!',
    
    // Estimate
    'estimate_set' => 'Estimate saved',
    'total_prayers' => 'Total Prayers',
    'start_recording_now' => 'Start recording now!',
    'estimate_usage' => 'Usage',
    'example' => 'Example',
    'or' => 'or',
    'estimate_cancelled' => '❌ Operation cancelled.',
    'estimate_unit_selected' => '📊 Estimate based on :unit\n\nHow many :unit of Qadha prayers do you have?\nPlease send a number:\n\nExample: 6',
    'estimate_invalid_number' => '❌ Please send a valid number (greater than zero).',
    'estimate_saved' => '✅ Your estimate has been saved!\n\n📊 New estimate:\n• :value :unit\n• Approximately :rakats rakats\n\n💡 This estimate is saved as your goal and will be shown in weekly reports.\n\nTo view stats: /stats',
    
    // Units
    'unit_day' => 'Day',
    'unit_week' => 'Week',
    'unit_month' => 'Month',
    'unit_year' => 'Year',
    'unit_rakat' => 'Rakat',
    
    // General
    'cancel' => 'Cancel',
    
    // Bot messages
    'welcome_prayer_bot' => 'Welcome to Prayer Qadha Bot',
    'prayer_bot_description' => 'Track your Qadha prayers easily',
    'send_number_instructions' => 'Just send a number',
    'available_commands' => 'Available commands',
    'view_stats' => 'View Stats',
    'set_estimate' => 'Set Estimate',
    'email_settings' => 'Email Settings',
    'help' => 'Help',
    
    // Help
    'prayer_bot_help' => 'Prayer Bot Help',
    'how_to_record' => 'How to record',
    'just_send_number' => 'Just send 2, 3 or 4',
    'how_to_remove' => 'How to remove',
    'use_remove_command' => 'Use /remove_<id> command',
    'how_to_estimate' => 'How to set estimate',
    'how_to_email' => 'How to set email',
    'smart_detection' => 'Smart Detection',
    'smart_detection_description' => 'Bot automatically detects prayer type',
    
    // Encouragement messages
    'encouragement_1' => '🌟 Excellent! Keep going!',
    'encouragement_2' => '💪 Great! Continue!',
    'encouragement_3' => '✨ May Allah accept!',
    'encouragement_4' => '🎯 Getting closer to your goal!',
    'encouragement_5' => '🙏 May Allah help you!',
    'encouragement_6' => '💚 Every step counts!',
    'encouragement_7' => '🌙 May Allah grant you success!',
    
    // Email
    'email_settings_title' => 'Email Settings',
    'email_settings_description' => 'Register your email for weekly reports',
    'send_your_email' => 'Send your email',
    'email_settings_coming_soon' => 'Coming soon...',
    'daily' => 'Daily',
    'weekly' => 'Weekly',
    'monthly' => 'Monthly',
    'never' => 'Never',
    'set_email' => 'Set Email',
    
    // Error messages
    'prayer_bot_unknown_command' => 'Unknown command',
    'send_number_to_record' => 'Send a number to record',
    'or_use_commands' => 'Or use commands',
    
    // ==================== Help System ====================
    
    // Main Help
    'help_main_title' => '🕌 Prayer Qadha Bot Help',
    'help_main_welcome' => 'Welcome to Prayer Qadha Bot! This bot helps you to:',
    'help_main_features' => "✅ Record your Qadha prayers\n📊 Manage prayer estimates\n📧 Receive weekly reports\n📈 Track your progress",
    'help_main_select' => '📚 For more information, select one of the following:',
    'help_main_quick_start' => '💡 Quick start: Just send 2, 3 or 4!',
    
    // Help Buttons
    'help_btn_commands' => '📋 Commands',
    'help_btn_usage' => '📖 How to Use',
    'help_btn_estimate' => '📊 Estimate',
    'help_btn_report' => '📧 Email Report',
    'help_btn_faq' => '❓ FAQ',
    'help_btn_back_to_help' => '◀️ Back to Help',
    'help_btn_back_to_menu' => '🏠 Main Menu',
    
    // Help Commands
    'help_commands_title' => '📋 Commands List',
    'help_commands_main' => "🔹 Main Commands:\n• /start - Start the bot\n• /help - Show help\n• /stats - View statistics",
    'help_commands_record' => "🔹 Record Prayer:\n• Send 2 - Record Fajr (2 rakats)\n• Send 3 - Record Maghrib (3 rakats)\n• Send 4 - Record Dhuhr/Asr/Isha (4 rakats)\n• /remove_[ID] - Remove recorded prayer",
    'help_commands_estimate' => "🔹 Estimate:\n• /estimate - Set prayer estimate\n• /estimate_status - View current estimate",
    'help_commands_report' => "🔹 Report:\n• /set_email - Set email for reports\n• /email_settings - Email settings\n• /unsubscribe - Unsubscribe from emails",
    'help_commands_help' => "🔹 Help:\n• /help_usage - How to use\n• /help_estimate - Estimate guide\n• /help_report - Report guide\n• /help_faq - Frequently asked questions",
    'help_commands_tip' => '💡 Tip: You can use buttons instead of typing commands.',
    
    // Help Usage
    'help_usage_title' => '📖 How to Use the Bot',
    'help_usage_content' => "🔸 Step 1: Record Prayer\nTo record Qadha prayer, just send the number of rakats:\n\n• 2 → Fajr (2 rakats)\n• 3 → Maghrib (3 rakats)\n• 4 → Dhuhr, Asr or Isha (4 rakats)\n\nExample:\nYou: 4\nBot: ✅ 4 rakats (Dhuhr) recorded!\n\n🔸 Step 2: View Statistics\nUse /stats command to see your stats:\n• Total rakats recorded\n• Total prayers recorded\n• Progress towards estimate\n• Current week stats\n\n🔸 Step 3: Set Estimate (Optional)\nFor better tracking, set your prayer estimate:\n1. Send /estimate command\n2. Select unit (day, week, month, year, rakat)\n3. Enter the number\n\n🔸 Step 4: Receive Reports (Optional)\nTo receive weekly reports:\n1. Send /set_email command\n2. Enter your email\n3. Enter verification code\n\n💡 Note: Bot automatically detects prayer type.",
    
    // Help Estimate
    'help_estimate_title' => '📊 Estimate System Guide',
    'help_estimate_what' => '🎯 What is Estimate?\nEstimate is the total number of Qadha prayers you think you have.\nThis number helps you track your progress.',
    'help_estimate_how' => "📝 How to Set Estimate:\n\n1️⃣ Send /estimate command\n\n2️⃣ Select your preferred unit:\n   • Day: Number of days you missed prayers\n   • Week: Number of weeks\n   • Month: Number of months\n   • Year: Number of years\n   • Rakat: Direct number of rakats\n\n3️⃣ Enter the number\n\nExample:\nYou: /estimate\nBot: [Shows buttons]\nYou: [Click \"Month\"]\nBot: How many months?\nYou: 6\nBot: ✅ 6 months = 3060 rakats saved",
    'help_estimate_calc' => "🔢 Calculations:\n• Each day = 17 rakats (5 prayers)\n• Each week = 119 rakats (7 days)\n• Each month = 510 rakats (30 days)\n• Each year = 6205 rakats (365 days)",
    'help_estimate_progress' => "📈 Progress Display:\nAfter setting estimate, in /stats you'll see:\n\n🎯 Total Estimate: 3060 rakats\n✅ Recorded: 450 rakats (15%)\n📉 Remaining: 2610 rakats\n\n⏱️ Estimated Time: At this rate, about 8 months",
    'help_estimate_tip' => '💡 Note: New estimate replaces the previous one.',
    
    // Help Report
    'help_report_title' => '📧 Email Report System Guide',
    'help_report_what' => "📬 What is Weekly Report?\nEvery week you receive an email containing:\n• Number of prayers recorded this week\n• Progress towards estimate\n• Progress chart\n• Motivation and encouragement",
    'help_report_how' => "⚙️ How to Activate:\n\n1️⃣ Set Email:\n   /set_email\n\n2️⃣ Enter Email:\n   example@gmail.com\n\n3️⃣ Verify Email:\n   Enter the 6-digit code sent to you\n\n✅ Activated! From now on you'll receive weekly reports.",
    'help_report_settings' => "🔧 Settings:\n\n• View settings: /email_settings\n• Change email: /set_email (new email)\n• Unsubscribe: /unsubscribe",
    'help_report_time' => '📅 Sending Time:\nReports are sent every Sunday morning at 9 AM.',
    'help_report_security' => "🔒 Security:\n• Your email is secure\n• Only used for sending reports\n• You can unsubscribe anytime",
    'help_report_tip' => '💡 Note: Even if you don\'t record prayers in a week, you\'ll receive a motivational email!',
    
    // Help FAQ
    'help_faq_title' => '❓ Frequently Asked Questions (FAQ)',
    'help_faq_content' => "🔹 How do I record a prayer?\nJust send 2, 3 or 4.\n2 = Fajr, 3 = Maghrib, 4 = Dhuhr/Asr/Isha\n\n🔹 What if I recorded by mistake?\nUse /remove_[ID] command.\nID is shown in the bot's confirmation message.\n\n🔹 How does the bot detect prayer type?\nBased on number of rakats and time of day:\n• 2 rakats → Fajr\n• 3 rakats → Maghrib\n• 4 rakats → Dhuhr (before noon) or Asr/Isha (after noon)\n\n🔹 Do I have to set an estimate?\nNo, it's optional. But recommended for better tracking.\n\n🔹 My estimate is not accurate, what should I do?\nYou can set a new estimate anytime with /estimate\n\n🔹 How many times can I record prayers per day?\nUnlimited! Record as many as you pray.\n\n🔹 Can I see my previous prayers?\nYes, use /stats command to see recent prayers.\n\n🔹 When are email reports sent?\nEvery Sunday morning at 9 AM\n\n🔹 I didn't receive the email?\n• Check your Spam folder\n• Verify email with /email_settings\n• Register new email with /set_email\n\n🔹 Is my information secure?\nYes, all your information is confidential and secure.\n\n🔹 Is the bot free?\nYes, completely free with no limitations!\n\n🔹 Does the bot work on Bale too?\nYes, it works on both Telegram and Bale.\n\n💡 Have another question?\nContact support: @support",
    
    // ==================== Estimate Status ====================
    'estimate_status_title' => 'Estimate Status',
    'estimate_status_no_estimate' => 'You haven\'t set an estimate yet.\n\nUse /estimate command to set your estimate.',
    'estimate_status_total' => 'Total Estimate',
    'estimate_status_equivalent' => 'Equivalent',
    'estimate_status_recorded' => 'Recorded',
    'estimate_status_remaining' => 'Remaining',
    'estimate_status_progress' => 'Progress',
    
    // ==================== Email ====================
    'email_format_example' => 'Example: example@gmail.com',
    'email_invalid_format' => '❌ Invalid email format.\n\nPlease send a valid email address.\nExample: example@gmail.com',
    'email_code_sent' => 'Verification code has been sent to your email',
    'email_enter_code' => 'Please enter the 6-digit code sent to you:',
    'email_code_expires' => 'This code expires in',
    'minutes' => 'minutes',
    'email_code_invalid_format' => '❌ Code must be 6 digits.\n\nPlease send the 6-digit code.',
    'email_code_incorrect' => '❌ Verification code is incorrect.\n\nPlease try again.',
    'email_code_expired' => '❌ Verification code has expired.\n\nPlease use /set_email command and try again.',
    'email_verified_success' => 'Your email has been verified successfully!',
    'email_verified_message' => 'From now on, weekly reports will be sent to this email.',
    'email_report_frequency' => 'Report Frequency',
    'email_send_error' => '❌ Error sending email.\n\nPlease try again or contact support.',
    
    // Email Settings
    'email_settings_current_email' => 'Current Email',
    'email_settings_verified' => 'Verified',
    'email_settings_not_verified' => 'Not Verified',
    'email_settings_no_email' => 'No Email Registered',
    'email_settings_frequency' => 'Report Frequency',
    'email_settings_instructions' => 'To change report frequency, select one of the options below:',
    'email_settings_unsubscribe' => 'Unsubscribe',
    'email_settings_change_email' => 'Change Email',
    'email_already_set' => 'Your email is already set:',
    'email_change_instructions' => 'If you want to change it, use the buttons below:',
    'email_frequency_updated' => 'Report frequency has been updated',
    'email_unsubscribed' => 'Your subscription has been cancelled',
    'email_unsubscribed_message' => 'You will no longer receive email reports.\n\nTo reactivate, use /set_email command.',
    'email_verification_error' => 'Error verifying email',
    'please_try_again' => 'Please try again or contact support.',
    
    // Email Verification (for view)
    'email_verification_subject' => 'Email Verification Code - Prayer Qadha Bot',
    'email_verification_title' => 'Email Verification Code',
    'email_verification_greeting' => 'Hello,',
    'email_verification_message' => 'Your email verification code for Prayer Qadha Bot:',
    'email_verification_warning' => '⚠️ This code is valid for 10 minutes.\n⚠️ If you did not request this, please ignore this email.',
    'email_verification_footer' => 'This email was sent automatically. Please do not reply.',
    
    // ==================== Location ====================
    'location_request' => '📍 Please send your location.\n\nTo send location:\n1. Click on the 📎 icon\n2. Select Location\n3. Send your location',
    'location_saved' => '✅ Your location has been saved successfully',
    'location_not_set' => '❌ Location is not set.\n\nPlease use /location command and send your location.',
    'location_current' => 'Current location',
    'location_address' => 'Address',
    'location_invalid' => '❌ Invalid location sent.\n\nPlease try again.',
    
    // ==================== Weather Alerts ====================
    'alert_list' => 'Alert List',
    'no_alerts' => 'No alerts registered.',
    'alert_add' => 'Add Alert',
    'alert_deleted' => '✅ Alert deleted',
    'alert_not_found' => '❌ Alert not found',
    'alert_created' => '✅ Alert created successfully',
    'alert_limit_reached' => '❌ You have reached the maximum number of alerts ({max}).\n\nTo add more alerts, use Pro features.',
    'alert_triggered' => 'Alert triggered',
    'alert_location' => 'Location',
    'alert_count' => 'Alert count: {current} / {max}',
    'alert_select_type' => 'Select alert type:',
    'alert_temperature' => 'Temperature',
    'alert_precipitation' => 'Precipitation',
    'alert_wind' => 'Wind',
    'alert_snow' => 'Snow',
    'alert_type_temperature' => 'Temperature',
    'alert_type_precipitation' => 'Precipitation',
    'alert_type_wind' => 'Wind',
    'alert_type_snow' => 'Snow',
    'alert_comparison_increase' => 'Increase',
    'alert_comparison_decrease' => 'Decrease',
    'alert_comparison_absolute' => 'Absolute',
    'alert_set_threshold_temperature' => 'Please set the temperature change threshold (e.g., 5 for 5 degrees):',
    'alert_set_threshold_precipitation' => 'Please set the precipitation change threshold (e.g., 10 for 10 mm):',
    'alert_set_threshold_wind' => 'Please set the wind change threshold (e.g., 10 for 10 km/h):',
    'alert_set_threshold_snow' => 'Please set the snow change threshold (e.g., 5 for 5 mm):',
    'alert_example_threshold' => 'Example: 5 (for 5 degrees or 5 mm change)',
    'unlimited' => 'Unlimited',
    'delete' => 'Delete',
    
    // ==================== Pro ====================
    'pro_status' => 'Pro Status',
    'pro_active' => 'Pro is active',
    'pro_not_active' => 'Pro is not active',
    'pro_expires_at' => 'Expires at',
    'pro_buy' => 'Buy Pro',
    'pro_features_description' => "With Pro you can:" . PHP_EOL . "• Unlimited alerts" . PHP_EOL . "• Advanced reports" . PHP_EOL . "• And more features",
    'pro_required' => 'This feature requires Pro',
    'pro_feature_unlimited_alerts' => 'Unlimited alerts',
    'pro_buy_instruction' => 'Use /pro_buy command to purchase Pro',
    'pro_purchase_requested' => '✅ Your Pro purchase request has been registered',
    'pro_contact_admin' => 'Please contact admin: :admin',
    'pro_payment_info' => 'After payment, send payment information to admin.',
    'pro_purchase_error' => '❌ Error registering purchase request',
    'command_not_found' => '❌ Command not found\n\n',
    'weather_error' => '❌ Error getting weather data',
    'no_weather_data' => 'No weather data received',
    
    // Book Pixel Bot translations
    'book_pixel_welcome' => '📚 Welcome to Book Pixel Bot!\n\nThis bot allows you to share book pages.',
    'book_pixel_ask_book_name' => 'Please send the book name or ISBN/Shabak:',
    'book_pixel_ask_isbn' => 'Please send the book ISBN (optional):',
    'book_pixel_ask_shabak' => 'Please send the book Shabak (optional):',
    'book_pixel_ask_cover' => 'Please send the book cover image:',
    'book_pixel_ask_page_number' => 'Please send the page number:',
    'book_pixel_ask_scan' => 'Please send the page scan:',
    'book_pixel_ask_voice' => 'Please send the page voice (optional):',
    'book_pixel_book_not_found' => 'Book not found. Please try again.',
    'book_pixel_book_found' => 'Book "{name}" found.',
    'book_pixel_book_created' => 'Book "{name}" created successfully.',
    'book_pixel_scan_sent_for_approval' => '✅ Page scan sent for approval. After approval, it will be added to the publishing queue.',
    'book_pixel_voice_sent' => '✅ Voice sent successfully.',
    'book_pixel_score_message' => '📊 Your Score:\n\n🎯 Total Points: {points}\n📄 Scans: {scans}\n🎤 Voices: {voices}',
    'book_pixel_no_score_yet' => 'You don\'t have any points yet. Earn points by sending scans and voices!',
    'book_pixel_invalid_page_number' => 'Invalid page number. Please send a valid number.',
    'book_pixel_missing_data' => 'Missing data. Please start again.',
    'book_pixel_unexpected_photo' => 'Please send /start command first.',
    'book_pixel_no_scan_for_voice' => 'You must first send a page scan.',
    'book_pixel_please_send_cover' => 'Please send the book cover image.',
    'book_pixel_please_send_scan' => 'Please send the page scan.',
];
