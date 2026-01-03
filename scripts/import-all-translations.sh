#!/bin/bash

# ایمپورت همه ترجمه‌های قرآن از پوشه resources/trans/
# این اسکریپت بعد از تست موفق دستورات استفاده می‌شود

cd "$(dirname "$0")/.."

echo "🚀 شروع ایمپورت همه ترجمه‌های قرآن..."
echo ""

php artisan quran-translations:import-all --path=resources/trans

echo ""
echo "✅ عملیات تکمیل شد."
