<?php

namespace App\Interfaces\Services;

interface GrowthLlmProvider
{
    /**
     * @return list<string>
     */
    public function generateVariants(
        string $intent,
        string $domain,
        int $difficulty,
        string $locale,
        int $count = 3
    ): array;
}
