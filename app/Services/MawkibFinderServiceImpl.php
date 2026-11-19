<?php

namespace App\Services;

use App\Interfaces\Services\MawkibFinderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MawkibFinderServiceImpl implements MawkibFinderService
{
    public function verifyIdentity(string $mobile, string $nationalCode): bool
    {
        $url = config('mawkib_finder.verify_url');

        if (empty($url)) {
            return $this->mockVerifyIdentity($nationalCode);
        }

        try {
            $response = Http::timeout(config('mawkib_finder.timeout', 30))
                ->when(!config('mawkib_finder.verify_ssl', true), fn ($client) => $client->withoutVerifying())
                ->post($url, [
                    'mobile' => $this->formatMobileWithPlus($mobile),
                    'national_code' => $nationalCode,
                ]);

            Log::info('[MawkibFinder] verifyIdentity response', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            if (!$response->successful()) {
                return false;
            }

            $body = $response->json() ?? $response->body();

            return $this->parseBooleanResponse($body);
        } catch (\Throwable $e) {
            Log::error('[MawkibFinder] verifyIdentity failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getAvailability(string $province, ?string $fromDate = null, ?string $toDate = null): array
    {
        $url = config('mawkib_finder.availability_url');

        if (empty($url)) {
            return $this->mockAvailability($province);
        }

        $requestUrl = $url;
        if ($fromDate !== null && $toDate !== null) {
            $requestUrl .= (str_contains($url, '?') ? '&' : '?') . http_build_query([
                'from' => $fromDate,
                'to' => $toDate,
            ]);
        }

        try {
            $response = Http::timeout(config('mawkib_finder.timeout', 30))
                ->when(!config('mawkib_finder.verify_ssl', true), fn ($client) => $client->withoutVerifying())
                ->post($requestUrl, [
                    'province' => $province,
                ]);

            Log::info('[MawkibFinder] getAvailability response', [
                'url' => $requestUrl,
                'province' => $province,
                'from' => $fromDate,
                'to' => $toDate,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            if (!$response->successful()) {
                return [];
            }

            return $this->parseAvailabilityResponse($response->json() ?? []);
        } catch (\Throwable $e) {
            Log::error('[MawkibFinder] getAvailability failed', [
                'error' => $e->getMessage(),
                'province' => $province,
            ]);

            return [];
        }
    }

    public function isUsingMockVerify(): bool
    {
        return empty(config('mawkib_finder.verify_url'));
    }

    public function isUsingMockAvailability(): bool
    {
        return empty(config('mawkib_finder.availability_url'));
    }

    /**
     * Mock: even last digit => true, odd last digit => false.
     */
    private function mockVerifyIdentity(string $nationalCode): bool
    {
        $lastDigit = (int) substr($nationalCode, -1);

        return $lastDigit % 2 === 0;
    }

    /**
     * @return array<int, array{city: string, vacant_count: int}>
     */
    private function mockAvailability(string $province): array
    {
        if ($province === 'قم') {
            return [
                ['city' => 'قم', 'vacant_count' => 1],
                ['city' => 'کهک', 'vacant_count' => 2],
                ['city' => 'قنوات', 'vacant_count' => 3],
            ];
        }

        return [
            ['city' => $province, 'vacant_count' => 0],
        ];
    }

    private function formatMobileWithPlus(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? $mobile;

        if (str_starts_with($digits, '98')) {
            return '+' . $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+98' . substr($digits, 1);
        }

        return '+' . $digits;
    }

    private function parseBooleanResponse(mixed $body): bool
    {
        if (is_bool($body)) {
            return $body;
        }

        if (is_string($body)) {
            $normalized = strtolower(trim($body));

            return in_array($normalized, ['true', '1', 'yes'], true);
        }

        if (is_array($body)) {
            foreach (['result', 'success', 'verified', 'valid', 'data'] as $key) {
                if (array_key_exists($key, $body)) {
                    return $this->parseBooleanResponse($body[$key]);
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, array{city: string, vacant_count: int}>
     */
    private function parseAvailabilityResponse(mixed $body): array
    {
        if (!is_array($body)) {
            return [];
        }

        $items = $body;
        foreach (['data', 'cities', 'result', 'items'] as $key) {
            if (isset($body[$key]) && is_array($body[$key])) {
                $items = $body[$key];
                break;
            }
        }

        if (!array_is_list($items)) {
            return [];
        }

        $parsed = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $city = $item['city'] ?? $item['name'] ?? $item['city_name'] ?? null;
            $count = $item['vacant_count'] ?? $item['count'] ?? $item['available'] ?? $item['vacant'] ?? null;

            if ($city === null || $count === null) {
                continue;
            }

            $parsed[] = [
                'city' => (string) $city,
                'vacant_count' => (int) $count,
            ];
        }

        return $parsed;
    }
}
