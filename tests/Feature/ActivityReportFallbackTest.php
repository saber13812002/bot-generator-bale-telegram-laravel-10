<?php

namespace Tests\Feature;

use App\Models\BotLog;
use App\Services\ActivityReportSummaryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\UsesAiProviderSqlite;
use Tests\UsesChannelPosterSqlite;

class ActivityReportFallbackTest extends TestCase
{
    use UsesAiProviderSqlite;
    use UsesChannelPosterSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAiProvidersTable();
        $this->createActiveAiProvider();

        Schema::dropIfExists('bot_logs');
        Schema::create('bot_logs', function (Blueprint $table) {
            $table->id();
            $table->string('webhook_endpoint_uri', 30);
            $table->unsignedBigInteger('bot_mother_id')->nullable();
            $table->string('language', 7)->nullable();
            $table->string('locale', 7)->nullable();
            $table->string('type')->nullable();
            $table->text('text');
            $table->boolean('is_command')->nullable();
            $table->bigInteger('channel_group_type')->nullable();
            $table->unsignedInteger('bot_id')->nullable();
            $table->bigInteger('chat_id');
            $table->bigInteger('message_id')->nullable();
            $table->bigInteger('from_id')->nullable();
            $table->bigInteger('from_chat_id')->nullable();
            $table->timestamps();
        });

        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('regexp', function ($a, $b) {
            $a = (string) $a;
            $b = (string) $b;
            $pattern = str_starts_with($a, '/') ? $a : $b;
            $value = $pattern === $a ? $b : $a;

            return (int) @preg_match($pattern, $value);
        }, 2);

        $this->seedBotLogs();
        Carbon::setTestNow(Carbon::parse('2026-10-09 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public function test_build_stats_and_cohort_for_seeded_user(): void
    {
        $service = new ActivityReportSummaryService();

        $stats = $service->buildStats(111, 'bale');
        $this->assertSame(3, $stats['total_7d']);
        $this->assertSame(3, $stats['streak_days']);
        $this->assertSame(0, $stats['total_prev_7d']);
        $this->assertSame(100.0, $stats['change_percent']);

        $cohort = $service->buildCohort(111, 'bale');
        $this->assertSame(2, $cohort['users']);
        $this->assertSame(3, $cohort['max']);
        $this->assertTrue($cohort['has_user']);
    }

    public function test_build_message_falls_back_when_llm_unavailable(): void
    {
        Http::fake(fn () => Http::response(['error' => 'boom'], 500));

        $message = (new ActivityReportSummaryService())->buildMessage(111, 'bale');

        $this->assertStringContainsString('در دسترس نیست', $message);
        $this->assertStringContainsString('📊 ۷ روز اخیر: 3 آیه', $message);
        $this->assertStringContainsString('👥 مقایسه با سایر کاربران (ناشناس):', $message);
    }

    public function test_build_message_includes_llm_analysis_when_available(): void
    {
        Http::fake(fn () => Http::response([
            'choices' => [['message' => ['content' => 'تحلیل: فعالیت شما عالی است و بالاتر از میانگین است.']]],
            'usage' => ['total_tokens' => 10],
        ], 200));

        $message = (new ActivityReportSummaryService())->buildMessage(111, 'bale');

        $this->assertStringContainsString('💬 تحلیل هوشمند:', $message);
        $this->assertStringContainsString('فعالیت شما عالی است و بالاتر از میانگین است.', $message);
        $this->assertStringNotContainsString('در دسترس نیست', $message);
    }

    private function seedBotLogs(): void
    {
        $logs = [
            ['chat_id' => 111, 'created_at' => '2026-10-09 10:00:00'],
            ['chat_id' => 111, 'created_at' => '2026-10-08 09:30:00'],
            ['chat_id' => 111, 'created_at' => '2026-10-07 08:15:00'],
            ['chat_id' => 222, 'created_at' => '2026-10-09 07:00:00'],
            ['chat_id' => 222, 'created_at' => '2026-10-06 06:00:00'],
        ];

        foreach ($logs as $log) {
            BotLog::create([
                'webhook_endpoint_uri' => 'webhook-quran-word',
                'language' => 'fa',
                'type' => 'bale',
                'text' => 'sure1ayah5',
                'is_command' => true,
                'chat_id' => $log['chat_id'],
                'created_at' => $log['created_at'],
            ]);
        }
    }
}
