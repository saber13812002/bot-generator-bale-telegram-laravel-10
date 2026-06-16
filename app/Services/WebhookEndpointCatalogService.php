<?php

namespace App\Services;

use App\Models\WebhookEndpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WebhookEndpointCatalogService
{
    private const INTERNAL_IDS = ['admin-bots', 'get-chat-id'];

    public function __construct(
        private readonly WebhookEndpointDefaultImporter $importer
    ) {}

    public function getActiveEndpoints(bool $forOwnerIntro = false): Collection
    {
        $this->ensurePopulated();

        $query = WebhookEndpoint::where('is_active', true)->orderBy('name');

        if ($forOwnerIntro) {
            $query->whereNotIn('endpoint_id', self::INTERNAL_IDS);
        }

        return $query->get();
    }

    private function ensurePopulated(): void
    {
        $total = WebhookEndpoint::count();

        if ($total === 0) {
            Log::warning('WebhookEndpoint catalog is empty; importing defaults');
            $this->importer->import();

            return;
        }

        if (WebhookEndpoint::where('is_active', true)->count() === 0) {
            Log::warning('WebhookEndpoint catalog has no active endpoints; reactivating all');
            WebhookEndpoint::query()->update(['is_active' => true]);
        }
    }
}
