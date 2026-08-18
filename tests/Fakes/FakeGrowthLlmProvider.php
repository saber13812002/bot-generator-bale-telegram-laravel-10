<?php

namespace Tests\Fakes;

use App\Interfaces\Services\GrowthLlmProvider;

class FakeGrowthLlmProvider implements GrowthLlmProvider
{
    public function generateVariants(
        string $intent,
        string $domain,
        int $difficulty,
        string $locale,
        int $count = 3
    ): array {
        $out = [];
        for ($i = 1; $i <= $count; $i++) {
            $out[] = "Variant {$i} for {$domain}";
        }

        return $out;
    }
}
