<?php

namespace Tests\Unit;

use App\Services\RssFeedRegistrationService;
use PHPUnit\Framework\TestCase;

class RssFeedRegistrationServiceTest extends TestCase
{
    private RssFeedRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RssFeedRegistrationService();
    }

    public function test_normalizes_full_virgool_feed_url(): void
    {
        $url = 'https://virgool.io/feed/@samiusblythe';
        $this->assertSame($url, $this->service->normalizeFeedUrl($url));
    }

    public function test_normalizes_username_only(): void
    {
        $this->assertSame(
            'https://virgool.io/feed/@samiusblythe',
            $this->service->normalizeFeedUrl('@samiusblythe')
        );
    }

    public function test_normalizes_profile_url(): void
    {
        $this->assertSame(
            'https://virgool.io/feed/@samiusblythe',
            $this->service->normalizeFeedUrl('https://virgool.io/@samiusblythe')
        );
    }

    public function test_rejects_empty_input(): void
    {
        $this->assertNull($this->service->normalizeFeedUrl('   '));
    }
}
