<?php

namespace App\Console\Commands;

use App\Services\WebhookEndpointDefaultImporter;
use Illuminate\Console\Command;

class ImportWebhookEndpointsDefault extends Command
{
    protected $signature = 'webhook-endpoints:import-default 
                            {--force : بازنویسی رکوردهای موجود}
                            {--dry-run : نمایش لیست endpoint ها بدون ذخیره کردن}';

    protected $description = 'Import کردن endpoint های پیش‌فرض به جدول webhook_endpoints';

    public function __construct(
        private readonly WebhookEndpointDefaultImporter $importer
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 حالت dry-run فعال است - هیچ رکوردی ذخیره نخواهد شد.');
            $this->newLine();
        }

        $this->info('📖 در حال آماده‌سازی endpoint های پیش‌فرض...');
        $this->newLine();

        $endpoints = $this->importer->getDefaultEndpoints();
        $totalEndpoints = count($endpoints);

        if ($totalEndpoints === 0) {
            $this->warn('⚠️  هیچ endpoint ای یافت نشد.');

            return 0;
        }

        $this->info("📊 تعداد endpoint های پیش‌فرض: {$totalEndpoints}");
        $this->newLine();

        $headers = ['Endpoint ID', 'Name', 'Route', 'Description'];
        $rows = [];

        foreach ($endpoints as $endpoint) {
            $rows[] = [
                $endpoint['id'],
                $endpoint['name'],
                $endpoint['route'],
                substr($endpoint['description'] ?? '', 0, 50) . (strlen($endpoint['description'] ?? '') > 50 ? '...' : ''),
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        if ($dryRun) {
            $this->info('ℹ️  حالت dry-run فعال است - هیچ رکوردی ذخیره نشد.');
            $this->info('برای ذخیره کردن واقعی، دستور را بدون --dry-run اجرا کنید.');

            return 0;
        }

        $this->info('💾 در حال ذخیره endpoint ها در جدول webhook_endpoints...');
        $this->newLine();

        $bar = $this->output->createProgressBar($totalEndpoints);
        $bar->start();

        try {
            $result = $this->importer->import($force);
        } catch (\Exception $e) {
            $bar->finish();
            $this->newLine(2);
            $this->error("❌ خطا در import: {$e->getMessage()}");

            return 1;
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('✅ Import انجام شد!');
        $this->table(
            ['وضعیت', 'تعداد'],
            [
                ['✅ ایجاد شده', $result['created']],
                ['🔄 به‌روزرسانی شده', $result['updated']],
                ['⏭️  رد شده', $result['skipped']],
                ['❌ خطا', $result['errors']],
                ['📊 کل', $totalEndpoints],
            ]
        );

        return $result['errors'] > 0 ? 1 : 0;
    }
}
