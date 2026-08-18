<?php

namespace App\Services;

use App\Interfaces\Services\GrowthLlmProvider;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpGrowthLlmProvider implements GrowthLlmProvider
{
    public function __construct(private Client $client, private array $config)
    {
    }

    public function generateVariants(
        string $intent,
        string $domain,
        int $difficulty,
        string $locale,
        int $count = 3
    ): array {
        $apiKey = $this->config['api_key'] ?? null;
        if (!$apiKey) {
            return [];
        }

        $promptPath = resource_path('growth_prompts/variant_generation.v1.md');
        $template = is_file($promptPath) ? (string) file_get_contents($promptPath) : '';
        $prompt = strtr($template, [
            '{{intent}}' => $intent,
            '{{domain}}' => $domain,
            '{{difficulty}}' => (string) $difficulty,
            '{{locale}}' => $locale,
            '{{count}}' => (string) $count,
        ]);

        $base = rtrim((string) ($this->config['base_url'] ?? 'https://api.openai.com/v1'), '/');
        $model = (string) ($this->config['model'] ?? 'gpt-4o-mini');
        $timeout = (int) ($this->config['timeout'] ?? 20);

        try {
            $response = $this->client->post($base.'/chat/completions', [
                'timeout' => $timeout,
                'headers' => [
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => 0.7,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You only output a JSON array of question strings.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
            ]);

            $payload = json_decode((string) $response->getBody(), true);
            $content = $payload['choices'][0]['message']['content'] ?? '';

            return $this->parseVariants(is_string($content) ? $content : '', $count);
        } catch (Throwable $e) {
            Log::warning('[GrowthCompanion] LLM variant generation failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function parseVariants(string $content, int $count): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            if (preg_match('/\[.*\]/s', $content, $matches)) {
                $decoded = json_decode($matches[0], true);
            }
        }

        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = trim($item);
            }
            if (count($out) >= $count) {
                break;
            }
        }

        return $out;
    }
}
