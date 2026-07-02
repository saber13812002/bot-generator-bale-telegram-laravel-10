<?php

namespace App\Interfaces\Services;

interface MawkibFinderService
{
    /**
     * Verify mobile + national code against Hawzah registry.
     */
    public function verifyIdentity(string $mobile, string $nationalCode): bool;

    /**
     * Get vacant mawkib spots by province (optionally filtered by entry date / stay duration).
     *
     * @return array<int, array{city: string, vacant_count: int}>
     */
    public function getAvailability(string $province, ?string $entryDate = null, ?int $stayDays = null): array;

    public function isUsingMockVerify(): bool;

    public function isUsingMockAvailability(): bool;
}
