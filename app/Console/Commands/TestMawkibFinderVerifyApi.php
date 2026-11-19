<?php

namespace App\Console\Commands;

use App\Interfaces\Services\MawkibFinderService;
use App\Modules\BaleOtp\Support\PhoneNormalizer;
use Illuminate\Console\Command;

class TestMawkibFinderVerifyApi extends Command
{
    protected $signature = 'mawkib-finder:test-verify-api
                            {mobile : شماره موبایل (مثلاً 09123456789)}
                            {national_code : کد ملی ۱۰ رقمی}';

    protected $description = 'تست وب‌سرویس احراز هویت موکب‌یاب (موبایل + کد ملی)';

    public function handle(MawkibFinderService $service): int
    {
        $mobile = PhoneNormalizer::normalize($this->argument('mobile'));
        $nationalCode = preg_replace('/\D+/', '', $this->argument('national_code'));

        if ($mobile === null) {
            $this->error('❌ شماره موبایل نامعتبر است.');
            return 1;
        }

        if (!preg_match('/^\d{10}$/', $nationalCode ?? '')) {
            $this->error('❌ کد ملی باید ۱۰ رقم باشد.');
            return 1;
        }

        $url = config('mawkib_finder.verify_url');
        $this->info('🔗 Verify URL: ' . ($url ?: '(خالی — پاسخ mock)'));
        $this->line('📱 Mobile: +' . $mobile);
        $this->line('🪪 National code: ' . $nationalCode);
        $this->newLine();

        if ($service->isUsingMockVerify()) {
            $this->warn('⚠️  URL در .env تنظیم نشده — پاسخ mock:');
            $this->line('   رقم آخر کد ملی زوج → true | فرد → false');
        }

        $result = $service->verifyIdentity($mobile, $nationalCode);

        $this->info($result ? '✅ نتیجه: true (ثبت‌شده در سامانه)' : '❌ نتیجه: false (ثبت نشده)');

        return 0;
    }
}
