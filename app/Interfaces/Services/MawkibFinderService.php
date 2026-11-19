<?php

namespace App\Interfaces\Services;

interface MawkibFinderService
{
    /**
     * Verify mobile + national code against Hawzah registry.
     */
    public function verifyIdentity(string $mobile, string $nationalCode): bool;

    /**
     * Get vacant mawkib spots by province.
     * When $fromDate and $toDate are set, they are sent as query string: from=Y/m/d&to=Y/m/d
     *
     * @return array<int, array{city: string, vacant_count: int}>
     */
    public function getAvailability(string $province, ?string $fromDate = null, ?string $toDate = null): array;

    public function isUsingMockVerify(): bool;

    public function isUsingMockAvailability(): bool;
}
