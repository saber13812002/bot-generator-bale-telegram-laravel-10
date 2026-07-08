<?php

namespace App\Modules\AdminBots\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Helpers\TokenHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\BotRequest;
use App\Modules\AdminBots\Helpers\AdminBotsStateHelper;
use App\Modules\AdminBots\Services\AdminBotsService;
use App\Modules\BotOwner\Contracts\BotClaimServiceInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class AdminBotsController extends Controller
{
    public function __construct(
        private readonly AdminBotsService $adminBotsService,
        private readonly BotClaimServiceInterface $claimService,
    ) {
    }

    public function index(BotRequest $request)
    {
        Log::info('Admin Bots webhook received', [
            'origin' => $request->input('origin'),
            'bot_mother_id' => $request->input('bot_mother_id'),
        ]);

        try {
            $type = $request->input('origin', 'bale');
            $botMotherId = (int) $request->input('bot_mother_id', 1);
            $bot = $this->createBotInstance($request, $type);

            if (!$bot) {
                return response('token required', 400);
            }

            try {
                LogHelper::log($request, $type, $bot);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }

            $chatId = (string) $bot->ChatID();
            $text = trim($bot->Text() ?? '');
            $command = mb_strtolower($text);
            $owner = $this->adminBotsService->findOwnerByChatId($chatId);
            $state = AdminBotsStateHelper::getState($chatId);
            $stateData = AdminBotsStateHelper::getStateData($chatId);

            if ($command === '/start') {
                $this->handleStart($bot);
            } elseif ($command === '/bots') {
                $this->handleBots($bot, $owner);
            } elseif ($command === '/admin-bots' || $command === '/admin_bots') {
                $this->handleAdminMenu($bot, $owner);
            } elseif (str_starts_with($command, '/link')) {
                $this->handleLink($bot, $text, $chatId, $owner);
            } elseif ($command === '/pro_request') {
                $this->handleProRequest($bot, $owner);
            } elseif ($command === '/create') {
                $this->handleCreateStart($bot, $owner);
            } elseif (str_starts_with($command, '/claim ')) {
                $this->handleClaim($bot, $text, $chatId, $type);
            } elseif ($command === '/help' || $command === 'راهنما') {
                $this->handleHelp($bot);
            } elseif ($state === AdminBotsStateHelper::STATE_WAITING_ENDPOINT) {
                $this->handleEndpointSelection($bot, $text, $chatId, $owner, $botMotherId);
            } elseif ($state === AdminBotsStateHelper::STATE_WAITING_PLATFORM) {
                $this->handlePlatformSelection($bot, $text, $chatId, $stateData, $owner, $botMotherId);
            } elseif ($state === AdminBotsStateHelper::STATE_WAITING_TOKEN) {
                $this->handleTokenInput($bot, $text, $chatId, $stateData, $owner, $botMotherId);
            } else {
                $this->handleHelp($bot);
            }

            return response('ok');
        } catch (Exception $e) {
            Log::error('Admin Bots webhook error', ['error' => $e->getMessage()]);

            return response('error', 500);
        }
    }

    private function createBotInstance(BotRequest $request, string $type): ?Telegram
    {
        $token = $request->input('token');
        if (!$token) {
            $token = $type === 'bale'
                ? env('ADMIN_BOTS_TOKEN_BALE')
                : env('ADMIN_BOTS_TOKEN_TELEGRAM');
        }

        if (!$token) {
            return null;
        }

        return $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
    }

    private function handleStart(Telegram $bot): void
    {
        $url = route('bot-owner.intro');
        $message = trans('bot-owner.admin_bots_start', ['url' => $url]);
        BotHelper::sendMessage($bot, $message);
    }

    private function handleBots(Telegram $bot, $owner): void
    {
        if (!$owner) {
            BotHelper::sendMessage($bot, trans('bot-owner.link_required'));
            return;
        }

        BotHelper::sendMessage($bot, $this->adminBotsService->listBots($owner));
    }

    private function handleAdminMenu(Telegram $bot, $owner): void
    {
        $message = trans('bot-owner.admin_bots_menu', ['url' => route('bot-owner.dashboard')]);
        BotHelper::sendMessage($bot, $message);

        if ($owner && !$owner->hasActivePro()) {
            BotHelper::sendMessage($bot, trans('bot-owner.pro_upgrade_hint'));
        }
    }

    private function handleLink(Telegram $bot, string $text, string $chatId, $owner): void
    {
        if ($owner) {
            BotHelper::sendMessage($bot, trans('bot-owner.already_linked'));
            return;
        }

        $parts = explode(' ', trim($text));
        $phone = $parts[1] ?? null;

        if (!$phone) {
            BotHelper::sendMessage($bot, trans('bot-owner.link_usage'));
            return;
        }

        $result = $this->adminBotsService->linkOwnerByPhone($chatId, $phone);
        BotHelper::sendMessage($bot, $result['message']);
    }

    private function handleProRequest(Telegram $bot, $owner): void
    {
        if (!$owner) {
            BotHelper::sendMessage($bot, trans('bot-owner.link_required'));
            return;
        }

        $result = $this->adminBotsService->requestPro($owner);
        BotHelper::sendMessage($bot, $result['message']);
    }

    private function handleCreateStart(Telegram $bot, $owner): void
    {
        if (!$owner) {
            BotHelper::sendMessage($bot, trans('bot-owner.link_required'));
            return;
        }

        if (!$owner->hasActivePro()) {
            BotHelper::sendMessage($bot, trans('bot-owner.pro_required'));
            return;
        }

        $endpoints = $this->adminBotsService->getEndpointsList();
        $message = trans('bot-owner.select_endpoint') . "\n\n";
        foreach ($endpoints as $index => $endpoint) {
            $message .= ($index + 1) . ". {$endpoint['name']}\n";
        }

        AdminBotsStateHelper::setState((string) $bot->ChatID(), AdminBotsStateHelper::STATE_WAITING_ENDPOINT, [
            'endpoints' => $endpoints,
        ]);

        BotHelper::sendMessage($bot, $message);
    }

    private function handleEndpointSelection(Telegram $bot, string $text, string $chatId, $owner, int $botMotherId): void
    {
        if (!$owner || !$owner->hasActivePro()) {
            AdminBotsStateHelper::clearState($chatId);
            BotHelper::sendMessage($bot, trans('bot-owner.pro_required'));
            return;
        }

        $stateData = AdminBotsStateHelper::getStateData($chatId);
        $endpoints = $stateData['endpoints'] ?? [];
        $index = (int) $text - 1;

        if (!isset($endpoints[$index])) {
            BotHelper::sendMessage($bot, trans('bot-owner.invalid_selection'));
            return;
        }

        $endpoint = $endpoints[$index];
        AdminBotsStateHelper::setState($chatId, AdminBotsStateHelper::STATE_WAITING_PLATFORM, [
            'endpoint_id' => $endpoint['id'],
            'endpoint_name' => $endpoint['name'],
        ]);

        BotHelper::sendMessage($bot, trans('bot-owner.select_platform'));
    }

    private function handlePlatformSelection(Telegram $bot, string $text, string $chatId, array $stateData, $owner, int $botMotherId): void
    {
        $platform = mb_strtolower(trim($text));
        if (!in_array($platform, ['telegram', 'bale', '1', '2'], true)) {
            BotHelper::sendMessage($bot, trans('bot-owner.select_platform'));
            return;
        }

        if ($platform === '1') {
            $platform = 'telegram';
        } elseif ($platform === '2') {
            $platform = 'bale';
        }

        AdminBotsStateHelper::setState($chatId, AdminBotsStateHelper::STATE_WAITING_TOKEN, array_merge($stateData, [
            'platform' => $platform,
        ]));

        BotHelper::sendMessage($bot, trans('bot-owner.send_token'));
    }

    private function handleTokenInput(Telegram $bot, string $text, string $chatId, array $stateData, $owner, int $botMotherId): void
    {
        $platform = $stateData['platform'] ?? 'bale';
        $endpointId = $stateData['endpoint_id'] ?? '';

        if (!TokenHelper::isToken($text, $platform)) {
            BotHelper::sendMessage($bot, trans('bot-owner.invalid_token'));
            return;
        }

        $result = $this->adminBotsService->registerBotFromMessenger(
            $owner,
            $chatId,
            $text,
            $endpointId,
            $platform,
            $botMotherId,
        );

        AdminBotsStateHelper::clearState($chatId);
        BotHelper::sendMessage($bot, $result['message']);
    }

    private function handleClaim(Telegram $bot, string $text, string $chatId, string $origin): void
    {
        $code = trim(substr($text, 7)); // Remove "/claim " prefix

        if (empty($code)) {
            BotHelper::sendMessage($bot, trans('bot-owner.claim_usage'));
            return;
        }

        $result = $this->claimService->verifyClaim($code, $chatId, $origin);
        BotHelper::sendMessage($bot, $result['message']);
    }

    private function handleHelp(Telegram $bot): void
    {
        BotHelper::sendMessage($bot, trans('bot-owner.admin_bots_help'));
    }
}
