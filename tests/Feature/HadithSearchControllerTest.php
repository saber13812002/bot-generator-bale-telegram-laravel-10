<?php

namespace Tests\Feature;

use App\Models\BotHadithItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HadithSearchControllerTest extends TestCase
{
//    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp(); // اطمینان حاصل کنید که این خط در اینجا قرار دارد.
        // ایجاد داده‌های اولیه در دیتابیس اگر نیاز باشد
        BotHadithItem::factory()->count(10)->create(); // فرض کنید یک Factory برای BotHadithItem دارید
    }

    public function testIndexWithoutOrigin()
    {
        $response = $this->json('GET', '/webhook-hadith');
        $response->assertStatus(400);
        $this->assertEquals('origin not specified in query string', $response->json('message'));
    }

    public function testIndexWithBaleOrigin()
    {
        $response = $this->json('GET', '/webhook-hadith', ['origin' => 'bale']);
        $response->assertStatus(200); // یا وضعیت مناسب دیگر
    }

    public function testIndexWithTelegramOrigin()
    {
        $response = $this->json('GET', '/webhook-hadith', ['origin' => 'telegram']);
        $response->assertStatus(200); // یا وضعیت مناسب دیگر
    }

    public function testRandomCommand()
    {
        $response = $this->json('POST', '/webhook-hadith', [
            'origin' => 'telegram',
            'text' => '/random'
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('link: to share in twitter or edit', $response->getContent());
    }

    public function testSearchCommand()
    {
        $response = $this->json('POST', '/webhook-hadith', [
            'origin' => 'telegram',
            'text' => '/search'
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('Please send your phrase to search', $response->getContent());
    }

    public function testHadithIdRequest()
    {
        $hadithId = 'some_id'; // ID حدیث معتبر را جایگزین کنید
        $response = $this->json('POST', '/webhook-hadith', [
            'origin' => 'telegram',
            'text' => "/_id:$hadithId"
        ]);

        $response->assertStatus(200);
        // بررسی وجود پیام مناسب
    }

    public function testIndexHandlesException()
    {
        // شبیه‌سازی یک استثنا
        $this->expectException(\Exception::class);

        // اجرای متد
        $response = $this->json('POST', '/webhook-hadith', ['origin' => 'telegram']);

        $response->assertStatus(404); // یا وضعیت مناسب دیگر
    }
}
