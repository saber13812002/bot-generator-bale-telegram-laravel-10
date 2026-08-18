<?php

namespace App\Services;

use App\Interfaces\Services\GrowthLlmProvider;

class NullGrowthLlmProvider implements GrowthLlmProvider
{
    public function generateVariants(
        string $intent,
        string $domain,
        int $difficulty,
        string $locale,
        int $count = 3
    ): array {
        return [];
    }
}
