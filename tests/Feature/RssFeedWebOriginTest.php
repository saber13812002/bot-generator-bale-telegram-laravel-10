<?php

namespace Tests\Feature;

use App\Models\RssFeedWebOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class RssFeedWebOriginTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function store_creates_rss_feed()
    {
        $data = [
            'origin' => 'example.com',
            'media_id' => '123',
            'image' => 'http://example.com/image.jpg',
            'link' => 'http://example.com',
            'title' => 'Example Title',
            'description' => 'Example Description',
        ];

        $response = $this->postJson('/api/rss-feed', $data);

        $response->assertStatus(Response::HTTP_CONFLICT)
            ->assertJson([
                'message' => 'RSS feed created successfully',
                'data' => [
                    'origin' => 'example.com',
                    'media_id' => '123',
                    'image' => 'http://example.com/image.jpg',
                    'link' => 'http://example.com',
                    'title' => 'Example Title',
                    'description' => 'Example Description',
                ]
            ]);

        $this->assertDatabaseHas('rss_feed_web_origins', $data);
    }

    public function test_store_returns_error_on_duplicate_entry()
    {
        $media_id = rand(10000, 99999);
        $data = [
            'origin' => 'example.com',
            'media_id' => '123' . $media_id,
            'image' => 'http://example.com/image.jpg',
            'link' => 'http://example.com/' . $media_id,
            'title' => 'Example Title',
            'description' => 'Example Description',
        ];

        RssFeedWebOrigin::create($data); // Create initial entry

        $response = $this->postJson('/api/rss-feed', $data);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function a_store_returns_error_on_duplicate_entry()
    {
        $data = [
            'origin' => 'example.com',
            'media_id' => '123',
            'image' => 'http://example.com/image.jpg',
            'link' => 'http://example.com',
            'title' => 'Example Title',
            'description' => 'Example Description',
        ];

        RssFeedWebOrigin::create($data); // Create initial entry

        try {
            $response = $this->postJson('/api/rss-feed', $data);

            $response->assertStatus(Response::HTTP_CONFLICT)
                ->assertJson([
                    'error' => 'This entry already exists.'
                ]);
        } catch (\Exception $e) {
            $this->fail('Exception was thrown: ' . $e->getMessage());
        }
    }
}
