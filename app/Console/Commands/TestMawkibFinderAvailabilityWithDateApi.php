<?php

namespace App\Console\Commands;

use App\Interfaces\Services\MawkibFinderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TestMawkibFinderAvailabilityWithDateApi extends Command
{
    protected $signature = 'mawkib-finder:test-availability-with-date-api
                            {province=قم : نام استان}
                            {--days=2 : تعداد روز از امروز برای تاریخ ورود (مثلاً 2 یا 3)}
                            {--stay=3 : تعداد روز اقامت}';

    protected $description = 'تست وب‌سرویس ظرفیت موکب با تاریخ ورود و مدت اقامت';

    public function handle(MawkibFinderService $service): int
    {
        $province = $this->argument('province');
        $daysAhead = max(1, (int) $this->option('days'));
        $stayDays = max(1, min(14, (int) $this->option('stay')));
        $entryDate = Carbon::today()->addDays($daysAhead)->format('Y-m-d');

        $url = config('mawkib_finder.availability_url');

        $this->info('🔗 Availability URL: ' . ($url ?: '(خالی — پاسخ mock)'));
        $this->line('📍 Province: ' . $province);
        $this->line('📅 Entry date: ' . $entryDate . " ({$daysAhead} روز آینده)");
        $this->line('🛏 Stay days: ' . $stayDays);
        $this->newLine();

        if ($service->isUsingMockAvailability()) {
            $this->warn('⚠️  URL در .env تنظیم نشده — پاسخ mock (قم/کهک/قنوات: ۱/۲/۳)');
        }

        $results = $service->getAvailability($province, $entryDate, $stayDays);

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
