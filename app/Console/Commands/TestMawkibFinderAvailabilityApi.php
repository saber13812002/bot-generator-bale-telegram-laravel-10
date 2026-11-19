<?php

namespace App\Console\Commands;

use App\Interfaces\Services\MawkibFinderService;
use Illuminate\Console\Command;

class TestMawkibFinderAvailabilityApi extends Command
{
    protected $signature = 'mawkib-finder:test-availability-api
                            {province=قم : نام استان}';

    protected $description = 'تست وب‌سرویس ظرفیت موکب بدون تاریخ (نتیجه امروز)';

    public function handle(MawkibFinderService $service): int
    {
        $province = $this->argument('province');
        $url = config('mawkib_finder.availability_url');

        $this->info('🔗 Availability URL: ' . ($url ?: '(خالی — پاسخ mock)'));
        $this->line('📍 Province: ' . $province);
        $this->line('📅 Entry date: (none — today)');
        $this->newLine();

        if ($service->isUsingMockAvailability()) {
            $this->warn('⚠️  URL در .env تنظیم نشده — پاسخ mock (قم/کهک/قنوات: ۱/۲/۳)');
        }

        $results = $service->getAvailability($province);

        if ($results === []) {
            $this->error('❌ نتیجه‌ای دریافت نشد.');
            return 1;
        }

        $this->info('✅ نتایج:');
        foreach ($results as $item) {
            $this->line(sprintf('   🏙 %s: %d جای خالی', $item['city'], $item['vacant_count']));
        }

        return 0;
    }
}
