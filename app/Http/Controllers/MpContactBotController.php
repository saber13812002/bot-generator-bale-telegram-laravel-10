<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Interfaces\Services\MpContactBotService;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Models\MpContactPollVote;
use App\Models\MpContactTicket;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class MpContactBotController extends Controller
{
    private const ENDPOINT_ID = 'webhook-mp-contact';
    private const CALLBACK_PREFIX = 'mpc:';
    private const TICKETS_PER_PAGE = 10;
    private const POLLS_PER_PAGE = 5;

    private const STATE_AWAITING_TICKET = 'mpc_awaiting_ticket';
    private const STATE_AWAITING_TRACK = 'mpc_awaiting_track';
    private const STATE_AWAITING_POLL_COMMENT = 'mpc_awaiting_poll_comment';
    private const STATE_AWAITING_POLL_CREATE = 'mpc_awaiting_poll_create';
    private const STATE_AWAITING_POLL_EDIT = 'mpc_awaiting_poll_edit';
    private const STATE_AWAITING_ADMIN_FORWARD = 'mpc_awaiting_admin_forward';

    public function __construct(
        private MpContactBotService $service
    ) {}

    public function index(\App\Http\Requests\BotRequest $request)
    {
        Log::info('🔔 [MpContact] Webhook received', [
            'origin' => $request->input('origin'),
            'has_token' => $request->has('token'),
        ]);

        try {
            $type = $request->input('origin', 'telegram');
            $token = $request->input('token');
            if (empty($token)) {
                Log::warning('[MpContact] Missing token');
                return response()->json(['status' => 'error'], 200);
            }

            $bot = $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
            $update = $request->json()->all() ?? $request->all();
            $botItem = $this->getBotByToken($token, $type);

            if (!$botItem || $botItem->endpoint_id !== self::ENDPOINT_ID) {
                Log::warning('[MpContact] Bot not found or wrong endpoint');
                return response()->json(['status' => 'error'], 200);
            }

            $this->service->ensurePrimaryAdmin($botItem, $type);

            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $botItem, $type);
                return response()->json(['status' => 'ok'], 200);
            }

            $message = $update['message'] ?? $update['edited_message'] ?? null;
            $text = $message['text'] ?? $bot->Text();
            $chatId = (string) ($message['chat']['id'] ?? $bot->ChatID());

            if ($this->isForwardedPrivateUser($message) && $this->service->isAdmin($botItem->id, $chatId, $type)) {
                $this->handleAdminForward($bot, $message, $botItem, $type, $chatId);
                return response()->json(['status' => 'ok'], 200);
            }

            if ($text === '/start' || str_starts_with((string) ($text ?? ''), '/start ')) {
                $this->clearState($botItem, $chatId, $type);
                $this->handleStart($bot, $botItem, $type, $chatId);
                return response()->json(['status' => 'ok'], 200);
            }

            if ($text === '/admin_kiye' || str_starts_with((string) ($text ?? ''), '/admin_kiye')) {
                $this->handleAdminKiye($bot, $botItem, $type, $chatId);
                return response()->json(['status' => 'ok'], 200);
            }

            if ($text !== null && $text !== '') {
                $this->handleTextMessage($bot, (string) $text, $botItem, $type, $chatId);
            }

            return response()->json(['status' => 'ok'], 200);
        } catch (Exception $e) {
            Log::error('[MpContact] Exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }
    }

    private function getBotByToken(string $token, string $type): ?Bot
    {
        if ($type === 'bale') {
            return Bot::where('bale_bot_token', $token)->first();
        }

        return Bot::where('telegram_bot_token', $token)->first();
    }

    private function handleStart(Telegram $bot, Bot $botItem, string $type, string $chatId): void
    {
        $isAdmin = $this->service->isAdmin($botItem->id, $chatId, $type);
        $message = $isAdmin
            ? trans('bot.mp_contact_welcome_admin')
            : trans('bot.mp_contact_welcome');

        $rows = [];
        $rows[] = [[
            'text' => trans('bot.mp_contact_btn_send_ticket'),
            'callback_data' => self::CALLBACK_PREFIX . 'u:ticket',
        ]];
        $rows[] = [[
            'text' => trans('bot.mp_contact_btn_track_ticket'),
            'callback_data' => self::CALLBACK_PREFIX . 'u:track',
        ]];
        $rows[] = [[
            'text' => trans('bot.mp_contact_btn_polls'),
            'callback_data' => self::CALLBACK_PREFIX . 'u:polls:1',
        ]];

        if ($isAdmin) {
            $rows[] = [[
                'text' => trans('bot.mp_contact_btn_admin_tickets'),
                'callback_data' => self::CALLBACK_PREFIX . 'a:tickets:1',
            ]];
            $rows[] = [[
                'text' => trans('bot.mp_contact_btn_admin_polls'),
                'callback_data' => self::CALLBACK_PREFIX . 'a:polls:1',
            ]];
            $rows[] = [[
                'text' => trans('bot.mp_contact_btn_admin_manage'),
                'callback_data' => self::CALLBACK_PREFIX . 'a:admins',
            ]];
        }

        $this->sendMenu($bot, $chatId, $message, $rows, $type);
    }

    private function handleAdminKiye(Telegram $bot, Bot $botItem, string $type, string $chatId): void
    {
        $result = $this->service->createAdminRequest($botItem->id, $chatId, $type);

        if (!$result['ok']) {
            $msg = $result['reason'] === 'already_admin'
                ? trans('bot.mp_contact_already_admin')
                : trans('bot.mp_contact_admin_request_pending');
            BotHelper::sendMessageByChatId($bot, $chatId, $msg);
            return;
        }

        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_admin_request_sent'));

        $request = $result['request'];
        $primaries = \App\Models\MpContactAdmin::where('bot_id', $botItem->id)
            ->where('is_primary', true)
            ->where('origin', $type)
            ->get();

        $notifyText = trans('bot.mp_contact_admin_request_notify', [
            'chat_id' => $chatId,
            'id' => $request->id,
        ]);

        $rows = [[
            [
                'text' => trans('bot.mp_contact_btn_approve'),
                'callback_data' => self::CALLBACK_PREFIX . 'a:areq:ok:' . $request->id,
            ],
            [
                'text' => trans('bot.mp_contact_btn_reject'),
                'callback_data' => self::CALLBACK_PREFIX . 'a:areq:no:' . $request->id,
            ],
        ]];

        foreach ($primaries as $primary) {
            $this->sendMenu($bot, $primary->chat_id, $notifyText, $rows, $type);
        }
    }

    private function handleTextMessage(Telegram $bot, string $text, Bot $botItem, string $type, string $chatId): void
    {
        $state = $this->getState($botItem, $chatId, $type);
        if (!$state) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_use_start'));
            return;
        }

        $current = $state->state;
        $data = $state->data ?? [];

        if ($current === self::STATE_AWAITING_TICKET) {
            $ticket = $this->service->createTicket($botItem->id, $chatId, $type, $text);
            $this->clearState($botItem, $chatId, $type);
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_ticket_created', [
                'code' => $ticket->tracking_code,
            ]));
            $this->handleStart($bot, $botItem, $type, $chatId);
            return;
        }

        if ($current === self::STATE_AWAITING_TRACK) {
            $ticket = $this->service->findTicketByTrackingCode($botItem->id, $text, $chatId);
            $this->clearState($botItem, $chatId, $type);
            if (!$ticket) {
                BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_ticket_not_found'));
            } else {
                BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_ticket_status', [
                    'code' => $ticket->tracking_code,
                    'status' => $ticket->statusLabel(),
                    'body' => mb_substr($ticket->body, 0, 200),
                ]));
            }
            $this->handleStart($bot, $botItem, $type, $chatId);
            return;
        }

        if ($current === self::STATE_AWAITING_POLL_COMMENT) {
            $pollId = (int) ($data['poll_id'] ?? 0);
            $comment = $this->service->createPollComment($pollId, $botItem->id, $chatId, $type, $text);
            $this->clearState($botItem, $chatId, $type);
            if (!$comment) {
                BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_poll_unavailable'));
            } else {
                BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_comment_created', [
                    'code' => $comment->tracking_code,
                ]));
            }
            $this->handleStart($bot, $botItem, $type, $chatId);
            return;
        }

        if ($current === self::STATE_AWAITING_POLL_CREATE) {
            if (!$this->service->isAdmin($botItem->id, $chatId, $type)) {
                $this->clearState($botItem, $chatId, $type);
                return;
            }
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_POLL_CREATE, [
                'draft' => $text,
            ]);
            $rows = [[
                [
                    'text' => trans('bot.mp_contact_btn_publish'),
                    'callback_data' => self::CALLBACK_PREFIX . 'a:poll:publish',
                ],
                [
                    'text' => trans('bot.mp_contact_btn_cancel'),
                    'callback_data' => self::CALLBACK_PREFIX . 'a:menu',
                ],
            ]];
            $this->sendMenu($bot, $chatId, trans('bot.mp_contact_poll_confirm', ['body' => $text]), $rows, $type);
            return;
        }

        if ($current === self::STATE_AWAITING_POLL_EDIT) {
            if (!$this->service->isAdmin($botItem->id, $chatId, $type)) {
                $this->clearState($botItem, $chatId, $type);
                return;
            }
            $pollId = (int) ($data['poll_id'] ?? 0);
            $poll = $this->service->updatePollBody($pollId, $botItem->id, $text);
            $this->clearState($botItem, $chatId, $type);
            if ($poll) {
                BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_poll_updated'));
            } else {
                BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_poll_unavailable'));
            }
            $this->showAdminPolls($bot, $botItem, $type, $chatId, 1);
            return;
        }

        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_use_start'));
    }

    private function handleCallbackQuery(Telegram $bot, array $callback, Bot $botItem, string $type): void
    {
        $chatId = (string) ($callback['message']['chat']['id'] ?? $callback['from']['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $callbackId = $callback['id'] ?? null;

        if ($callbackId) {
            try {
                $bot->answerCallbackQuery(['callback_query_id' => $callbackId, 'text' => '']);
            } catch (Exception $e) {
                // ignore
            }
        }

        if (!str_starts_with($data, self::CALLBACK_PREFIX)) {
            return;
        }

        $payload = substr($data, strlen(self::CALLBACK_PREFIX));
        $parts = explode(':', $payload);
        $scope = $parts[0] ?? '';

        if ($scope === 'u') {
            $this->handleUserCallback($bot, $botItem, $type, $chatId, $parts);
            return;
        }

        if ($scope === 'a') {
            if (!$this->service->isAdmin($botItem->id, $chatId, $type)) {
                BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_not_admin'));
                return;
            }
            $this->handleAdminCallback($bot, $botItem, $type, $chatId, $parts);
        }
    }

    private function handleUserCallback(Telegram $bot, Bot $botItem, string $type, string $chatId, array $parts): void
    {
        $action = $parts[1] ?? '';

        if ($action === 'menu' || $action === 'home') {
            $this->clearState($botItem, $chatId, $type);
            $this->handleStart($bot, $botItem, $type, $chatId);
            return;
        }

        if ($action === 'ticket') {
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_TICKET);
            $rows = [[[
                'text' => trans('bot.mp_contact_btn_cancel'),
                'callback_data' => self::CALLBACK_PREFIX . 'u:home',
            ]]];
            $this->sendMenu($bot, $chatId, trans('bot.mp_contact_ask_ticket'), $rows, $type);
            return;
        }

        if ($action === 'track') {
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_TRACK);
            $rows = [[[
                'text' => trans('bot.mp_contact_btn_cancel'),
                'callback_data' => self::CALLBACK_PREFIX . 'u:home',
            ]]];
            $this->sendMenu($bot, $chatId, trans('bot.mp_contact_ask_tracking_code'), $rows, $type);
            return;
        }

        if ($action === 'polls') {
            $page = (int) ($parts[2] ?? 1);
            $this->showUserPolls($bot, $botItem, $type, $chatId, $page);
            return;
        }

        if ($action === 'poll') {
            $sub = $parts[2] ?? '';
            $pollId = (int) ($parts[3] ?? 0);

            if ($sub === 'view') {
                $this->showUserPollDetail($bot, $botItem, $type, $chatId, $pollId);
                return;
            }

            if ($sub === 'agree' || $sub === 'disagree') {
                $choice = $sub === 'agree'
                    ? MpContactPollVote::CHOICE_AGREE
                    : MpContactPollVote::CHOICE_DISAGREE;
                $result = $this->service->vote($pollId, $botItem->id, $chatId, $type, $choice);
                if (!$result['ok']) {
                    $msg = match ($result['reason'] ?? '') {
                        'already_voted' => trans('bot.mp_contact_already_voted'),
                        default => trans('bot.mp_contact_poll_unavailable'),
                    };
                    BotHelper::sendMessageByChatId($bot, $chatId, $msg);
                } else {
                    BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_vote_ok'));
                }
                $this->showUserPollDetail($bot, $botItem, $type, $chatId, $pollId);
                return;
            }

            if ($sub === 'comment') {
                $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_POLL_COMMENT, [
                    'poll_id' => $pollId,
                ]);
                $rows = [[[
                    'text' => trans('bot.mp_contact_btn_cancel'),
                    'callback_data' => self::CALLBACK_PREFIX . 'u:poll:view:' . $pollId,
                ]]];
                $this->sendMenu($bot, $chatId, trans('bot.mp_contact_ask_comment'), $rows, $type);
            }
        }
    }

    private function handleAdminCallback(Telegram $bot, Bot $botItem, string $type, string $chatId, array $parts): void
    {
        $action = $parts[1] ?? '';

        if ($action === 'menu') {
            $this->clearState($botItem, $chatId, $type);
            $this->handleStart($bot, $botItem, $type, $chatId);
            return;
        }

        if ($action === 'tickets') {
            $page = (int) ($parts[2] ?? 1);
            $this->showAdminTickets($bot, $botItem, $type, $chatId, $page);
            return;
        }

        if ($action === 'ticket') {
            $sub = $parts[2] ?? '';
            $ticketId = (int) ($parts[3] ?? 0);

            if ($sub === 'view') {
                $this->showAdminTicketDetail($bot, $botItem, $type, $chatId, $ticketId);
                return;
            }

            if ($sub === 'status') {
                $status = $parts[4] ?? '';
                $ticket = $this->service->updateTicketStatus($ticketId, $botItem->id, $status);
                if ($ticket) {
                    BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_ticket_status_updated'));
                    $this->showAdminTicketDetail($bot, $botItem, $type, $chatId, $ticketId);
                }
                return;
            }
        }

        if ($action === 'polls') {
            $page = (int) ($parts[2] ?? 1);
            $this->showAdminPolls($bot, $botItem, $type, $chatId, $page);
            return;
        }

        if ($action === 'poll') {
            $sub = $parts[2] ?? '';

            if ($sub === 'create') {
                $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_POLL_CREATE);
                $rows = [[[
                    'text' => trans('bot.mp_contact_btn_cancel'),
                    'callback_data' => self::CALLBACK_PREFIX . 'a:polls:1',
                ]]];
                $this->sendMenu($bot, $chatId, trans('bot.mp_contact_ask_poll_body'), $rows, $type);
                return;
            }

            if ($sub === 'publish') {
                $state = $this->getState($botItem, $chatId, $type);
                $draft = $state?->getData('draft');
                $this->clearState($botItem, $chatId, $type);
                if (!$draft) {
                    BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_poll_unavailable'));
                    return;
                }
                $this->service->createPoll($botItem->id, (string) $draft);
                BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_poll_published'));
                $this->showAdminPolls($bot, $botItem, $type, $chatId, 1);
                return;
            }

            $pollId = (int) ($parts[3] ?? 0);

            if ($sub === 'edit') {
                $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_POLL_EDIT, [
                    'poll_id' => $pollId,
                ]);
                $rows = [[[
                    'text' => trans('bot.mp_contact_btn_cancel'),
                    'callback_data' => self::CALLBACK_PREFIX . 'a:polls:1',
                ]]];
                $this->sendMenu($bot, $chatId, trans('bot.mp_contact_ask_poll_edit'), $rows, $type);
                return;
            }

            if ($sub === 'toggle') {
                $poll = $this->service->togglePollActive($pollId, $botItem->id);
                if ($poll) {
                    $msg = $poll->is_active
                        ? trans('bot.mp_contact_poll_activated')
                        : trans('bot.mp_contact_poll_deactivated');
                    BotHelper::sendMessageByChatId($bot, $chatId, $msg);
                }
                $this->showAdminPolls($bot, $botItem, $type, $chatId, 1);
                return;
            }

            if ($sub === 'results') {
                $this->showPollResults($bot, $botItem, $type, $chatId, $pollId);
                return;
            }
        }

        if ($action === 'admins') {
            $this->showAdminManage($bot, $botItem, $type, $chatId);
            return;
        }

        if ($action === 'areq') {
            $decision = $parts[2] ?? '';
            $requestId = (int) ($parts[3] ?? 0);

            if (!$this->service->isPrimaryAdmin($botItem->id, $chatId, $type)) {
                BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_primary_only'));
                return;
            }

            if ($decision === 'ok') {
                $req = $this->service->approveAdminRequest($requestId, $botItem->id);
                if ($req) {
                    BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_admin_approved'));
                    BotHelper::sendMessageByChatId($bot, $req->chat_id, trans('bot.mp_contact_you_are_admin'));
                }
            } elseif ($decision === 'no') {
                $req = $this->service->rejectAdminRequest($requestId, $botItem->id);
                if ($req) {
                    BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_admin_rejected'));
                    BotHelper::sendMessageByChatId($bot, $req->chat_id, trans('bot.mp_contact_admin_request_rejected_user'));
                }
            }
            $this->showAdminManage($bot, $botItem, $type, $chatId);
            return;
        }

        if ($action === 'fwd') {
            $sub = $parts[2] ?? '';
            $targetChatId = $parts[3] ?? '';
            $targetOrigin = $parts[4] ?? $type;

            if ($sub === 'add') {
                $this->service->addAdmin($botItem->id, $targetChatId, $targetOrigin, false);
                BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_admin_added'));
                BotHelper::sendMessageByChatId($bot, $targetChatId, trans('bot.mp_contact_you_are_admin'));
            } elseif ($sub === 'rm') {
                if ($this->service->removeAdmin($botItem->id, $targetChatId, $targetOrigin)) {
                    BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_admin_removed'));
                    BotHelper::sendMessageByChatId($bot, $targetChatId, trans('bot.mp_contact_you_removed_admin'));
                } else {
                    BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_cannot_remove_primary'));
                }
            }
            $this->showAdminManage($bot, $botItem, $type, $chatId);
        }
    }

    private function showUserPolls(Telegram $bot, Bot $botItem, string $type, string $chatId, int $page): void
    {
        $paginator = $this->service->paginatePolls($botItem->id, $page, self::POLLS_PER_PAGE, true);
        if ($paginator->total() === 0) {
            $rows = [[[
                'text' => trans('bot.mp_contact_btn_home'),
                'callback_data' => self::CALLBACK_PREFIX . 'u:home',
            ]]];
            $this->sendMenu($bot, $chatId, trans('bot.mp_contact_no_active_polls'), $rows, $type);
            return;
        }

        $rows = [];
        foreach ($paginator->items() as $poll) {
            $title = mb_substr(preg_replace("/\s+/", ' ', $poll->body) ?? $poll->body, 0, 40);
            $rows[] = [[
                'text' => $title,
                'callback_data' => self::CALLBACK_PREFIX . 'u:poll:view:' . $poll->id,
            ]];
        }

        $nav = $this->paginationRow('u:polls', $paginator->currentPage(), $paginator->lastPage());
        if ($nav) {
            $rows[] = $nav;
        }
        $rows[] = [[
            'text' => trans('bot.mp_contact_btn_home'),
            'callback_data' => self::CALLBACK_PREFIX . 'u:home',
        ]];

        $this->sendMenu($bot, $chatId, trans('bot.mp_contact_polls_list', [
            'page' => $paginator->currentPage(),
            'total' => $paginator->lastPage(),
        ]), $rows, $type);
    }

    private function showUserPollDetail(Telegram $bot, Bot $botItem, string $type, string $chatId, int $pollId): void
    {
        $poll = $this->service->findPoll($pollId, $botItem->id);
        if (!$poll || !$poll->is_active) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_poll_unavailable'));
            return;
        }

        $vote = $this->service->getUserVote($pollId, $chatId, $type);
        $rows = [];

        if (!$vote) {
            $rows[] = [
                [
                    'text' => trans('bot.mp_contact_btn_agree'),
                    'callback_data' => self::CALLBACK_PREFIX . 'u:poll:agree:' . $pollId,
                ],
                [
                    'text' => trans('bot.mp_contact_btn_disagree'),
                    'callback_data' => self::CALLBACK_PREFIX . 'u:poll:disagree:' . $pollId,
                ],
            ];
        }

        $rows[] = [[
            'text' => trans('bot.mp_contact_btn_comment'),
            'callback_data' => self::CALLBACK_PREFIX . 'u:poll:comment:' . $pollId,
        ]];
        $rows[] = [[
            'text' => trans('bot.mp_contact_btn_back'),
            'callback_data' => self::CALLBACK_PREFIX . 'u:polls:1',
        ]];

        $extra = $vote
            ? "\n\n" . trans('bot.mp_contact_your_vote', [
                'choice' => $vote->choice === MpContactPollVote::CHOICE_AGREE
                    ? trans('bot.mp_contact_btn_agree')
                    : trans('bot.mp_contact_btn_disagree'),
            ])
            : '';

        $this->sendMenu($bot, $chatId, $poll->body . $extra, $rows, $type);
    }

    private function showAdminTickets(Telegram $bot, Bot $botItem, string $type, string $chatId, int $page): void
    {
        $paginator = $this->service->paginateTickets($botItem->id, $page, self::TICKETS_PER_PAGE);
        if ($paginator->total() === 0) {
            $rows = [[[
                'text' => trans('bot.mp_contact_btn_home'),
                'callback_data' => self::CALLBACK_PREFIX . 'a:menu',
            ]]];
            $this->sendMenu($bot, $chatId, trans('bot.mp_contact_no_tickets'), $rows, $type);
            return;
        }

        $rows = [];
        foreach ($paginator->items() as $ticket) {
            /** @var MpContactTicket $ticket */
            $label = $ticket->tracking_code . ' — ' . mb_substr($ticket->body, 0, 30);
            $rows[] = [[
                'text' => $label,
                'callback_data' => self::CALLBACK_PREFIX . 'a:ticket:view:' . $ticket->id,
            ]];
        }

        $nav = $this->paginationRow('a:tickets', $paginator->currentPage(), $paginator->lastPage());
        if ($nav) {
            $rows[] = $nav;
        }
        $rows[] = [[
            'text' => trans('bot.mp_contact_btn_home'),
            'callback_data' => self::CALLBACK_PREFIX . 'a:menu',
        ]];

        $this->sendMenu($bot, $chatId, trans('bot.mp_contact_tickets_list', [
            'page' => $paginator->currentPage(),
            'total' => $paginator->lastPage(),
        ]), $rows, $type);
    }

    private function showAdminTicketDetail(Telegram $bot, Bot $botItem, string $type, string $chatId, int $ticketId): void
    {
        $ticket = MpContactTicket::where('id', $ticketId)->where('bot_id', $botItem->id)->first();
        if (!$ticket) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_ticket_not_found'));
            return;
        }

        $text = trans('bot.mp_contact_ticket_detail', [
            'code' => $ticket->tracking_code,
            'status' => $ticket->statusLabel(),
            'chat_id' => $ticket->chat_id,
            'body' => $ticket->body,
        ]);

        $rows = [[
            [
                'text' => trans('bot.mp_contact_status_pending'),
                'callback_data' => self::CALLBACK_PREFIX . 'a:ticket:status:' . $ticket->id . ':pending',
            ],
            [
                'text' => trans('bot.mp_contact_status_reviewing'),
                'callback_data' => self::CALLBACK_PREFIX . 'a:ticket:status:' . $ticket->id . ':reviewing',
            ],
            [
                'text' => trans('bot.mp_contact_status_closed'),
                'callback_data' => self::CALLBACK_PREFIX . 'a:ticket:status:' . $ticket->id . ':closed',
            ],
        ]];
        $rows[] = [[
            'text' => trans('bot.mp_contact_btn_back'),
            'callback_data' => self::CALLBACK_PREFIX . 'a:tickets:1',
        ]];

        $this->sendMenu($bot, $chatId, $text, $rows, $type);
    }

    private function showAdminPolls(Telegram $bot, Bot $botItem, string $type, string $chatId, int $page): void
    {
        $paginator = $this->service->paginatePolls($botItem->id, $page, self::POLLS_PER_PAGE, false);

        $rows = [[[
            'text' => trans('bot.mp_contact_btn_create_poll'),
            'callback_data' => self::CALLBACK_PREFIX . 'a:poll:create',
        ]]];

        if ($paginator->total() === 0) {
            $rows[] = [[
                'text' => trans('bot.mp_contact_btn_home'),
                'callback_data' => self::CALLBACK_PREFIX . 'a:menu',
            ]];
            $this->sendMenu($bot, $chatId, trans('bot.mp_contact_no_polls_admin'), $rows, $type);
            return;
        }

        foreach ($paginator->items() as $poll) {
            $flag = $poll->is_active ? '✅' : '⏸';
            $title = $flag . ' ' . mb_substr(preg_replace("/\s+/", ' ', $poll->body) ?? $poll->body, 0, 35);
            $rows[] = [[
                'text' => $title,
                'callback_data' => self::CALLBACK_PREFIX . 'a:poll:results:' . $poll->id,
            ]];
            $toggleLabel = $poll->is_active
                ? trans('bot.mp_contact_btn_deactivate')
                : trans('bot.mp_contact_btn_activate');
            $rows[] = [
                [
                    'text' => trans('bot.mp_contact_btn_edit'),
                    'callback_data' => self::CALLBACK_PREFIX . 'a:poll:edit:' . $poll->id,
                ],
                [
                    'text' => $toggleLabel,
                    'callback_data' => self::CALLBACK_PREFIX . 'a:poll:toggle:' . $poll->id,
                ],
                [
                    'text' => trans('bot.mp_contact_btn_results'),
                    'callback_data' => self::CALLBACK_PREFIX . 'a:poll:results:' . $poll->id,
                ],
            ];
        }

        $nav = $this->paginationRow('a:polls', $paginator->currentPage(), $paginator->lastPage());
        if ($nav) {
            $rows[] = $nav;
        }
        $rows[] = [[
            'text' => trans('bot.mp_contact_btn_home'),
            'callback_data' => self::CALLBACK_PREFIX . 'a:menu',
        ]];

        $this->sendMenu($bot, $chatId, trans('bot.mp_contact_admin_polls_list', [
            'page' => $paginator->currentPage(),
            'total' => $paginator->lastPage(),
        ]), $rows, $type);
    }

    private function showPollResults(Telegram $bot, Bot $botItem, string $type, string $chatId, int $pollId): void
    {
        $results = $this->service->getPollResults($pollId, $botItem->id);
        if (!$results) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_poll_unavailable'));
            return;
        }

        $text = trans('bot.mp_contact_poll_results', [
            'body' => $results['poll']->body,
            'total' => $results['total'],
            'agree' => $results['agree'],
            'disagree' => $results['disagree'],
            'comments' => $results['comments'],
        ]);

        $rows = [[[
            'text' => trans('bot.mp_contact_btn_back'),
            'callback_data' => self::CALLBACK_PREFIX . 'a:polls:1',
        ]]];
        $this->sendMenu($bot, $chatId, $text, $rows, $type);
    }

    private function showAdminManage(Telegram $bot, Bot $botItem, string $type, string $chatId): void
    {
        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_ADMIN_FORWARD);
        $pending = $this->service->getPendingAdminRequests($botItem->id);

        $text = trans('bot.mp_contact_admin_manage_help');
        $rows = [];

        foreach ($pending as $req) {
            $rows[] = [[
                'text' => trans('bot.mp_contact_pending_request_btn', [
                    'chat_id' => $req->chat_id,
                    'id' => $req->id,
                ]),
                'callback_data' => self::CALLBACK_PREFIX . 'a:admins',
            ]];
            $rows[] = [
                [
                    'text' => trans('bot.mp_contact_btn_approve') . ' #' . $req->id,
                    'callback_data' => self::CALLBACK_PREFIX . 'a:areq:ok:' . $req->id,
                ],
                [
                    'text' => trans('bot.mp_contact_btn_reject') . ' #' . $req->id,
                    'callback_data' => self::CALLBACK_PREFIX . 'a:areq:no:' . $req->id,
                ],
            ];
        }

        $rows[] = [[
            'text' => trans('bot.mp_contact_btn_home'),
            'callback_data' => self::CALLBACK_PREFIX . 'a:menu',
        ]];

        $this->sendMenu($bot, $chatId, $text, $rows, $type);
    }

    private function isForwardedPrivateUser(?array $message): bool
    {
        if (!$message) {
            return false;
        }

        return isset($message['forward_from']) && !empty($message['forward_from']['id']);
    }

    private function handleAdminForward(Telegram $bot, array $message, Bot $botItem, string $type, string $chatId): void
    {
        $forwardFrom = $message['forward_from'];
        $targetChatId = (string) $forwardFrom['id'];

        if ((string) $targetChatId === (string) $chatId) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mp_contact_cannot_self'));
            return;
        }

        $existing = $this->service->findAdmin($botItem->id, $targetChatId, $type);
        $name = trim(($forwardFrom['first_name'] ?? '') . ' ' . ($forwardFrom['last_name'] ?? ''));

        if ($existing) {
            $rows = [[
                [
                    'text' => trans('bot.mp_contact_btn_yes'),
                    'callback_data' => self::CALLBACK_PREFIX . 'a:fwd:rm:' . $targetChatId . ':' . $type,
                ],
                [
                    'text' => trans('bot.mp_contact_btn_no'),
                    'callback_data' => self::CALLBACK_PREFIX . 'a:admins',
                ],
            ]];
            $this->sendMenu($bot, $chatId, trans('bot.mp_contact_confirm_remove_admin', [
                'name' => $name !== '' ? $name : $targetChatId,
                'chat_id' => $targetChatId,
            ]), $rows, $type);
            return;
        }

        $rows = [[
            [
                'text' => trans('bot.mp_contact_btn_yes'),
                'callback_data' => self::CALLBACK_PREFIX . 'a:fwd:add:' . $targetChatId . ':' . $type,
            ],
            [
                'text' => trans('bot.mp_contact_btn_no'),
                'callback_data' => self::CALLBACK_PREFIX . 'a:admins',
            ],
        ]];
        $this->sendMenu($bot, $chatId, trans('bot.mp_contact_confirm_add_admin', [
            'name' => $name !== '' ? $name : $targetChatId,
            'chat_id' => $targetChatId,
        ]), $rows, $type);
    }

    /**
     * @return array<int, array{text: string, callback_data: string}>|null
     */
    private function paginationRow(string $prefix, int $page, int $lastPage): ?array
    {
        if ($lastPage <= 1) {
            return null;
        }

        $row = [];
        if ($page > 1) {
            $row[] = [
                'text' => trans('bot.mp_contact_btn_prev'),
                'callback_data' => self::CALLBACK_PREFIX . $prefix . ':' . ($page - 1),
            ];
        }
        if ($page < $lastPage) {
            $row[] = [
                'text' => trans('bot.mp_contact_btn_next'),
                'callback_data' => self::CALLBACK_PREFIX . $prefix . ':' . ($page + 1),
            ];
        }

        return $row ?: null;
    }

    private function sendMenu(Telegram $bot, string $chatId, string $message, array $rows, string $type): void
    {
        $option = [];
        foreach ($rows as $row) {
            $botRow = [];
            foreach ($row as $btn) {
                $botRow[] = $bot->buildInlineKeyBoardButton($btn['text'], callback_data: $btn['callback_data']);
            }
            if (!empty($botRow)) {
                $option[] = $botRow;
            }
        }
        $keyboard = $bot->buildInlineKeyBoard($option);
        BotHelper::sendKeyboardMessageToChatId($bot, $message, $keyboard, $chatId);
    }

    private function resolveBotUser(Bot $botItem, string $chatId, string $type): BotUsers
    {
        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->where('bot_id', $botItem->id)
            ->first();

        if (!$botUser) {
            $botUser = BotUsers::create([
                'chat_id' => $chatId,
                'bot_id' => $botItem->id,
                'origin' => $type,
                'status' => 'active',
            ]);
        }

        return $botUser;
    }

    private function motherId(Bot $botItem): int
    {
        return (int) ($botItem->bot_mother_id ?? $botItem->id);
    }

    private function getState(Bot $botItem, string $chatId, string $type): ?BotUserState
    {
        $botUser = $this->resolveBotUser($botItem, $chatId, $type);

        return BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $this->motherId($botItem))
            ->active()
            ->latest('id')
            ->first();
    }

    private function setState(Bot $botItem, string $chatId, string $type, string $state, array $data = []): void
    {
        $botUser = $this->resolveBotUser($botItem, $chatId, $type);
        $motherId = $this->motherId($botItem);

        BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $motherId)
            ->delete();

        BotUserState::create([
            'bot_user_id' => $botUser->id,
            'bot_mother_id' => $motherId,
            'state' => $state,
            'data' => $data,
            'expires_at' => now()->addHours(6),
        ]);
    }

    private function clearState(Bot $botItem, string $chatId, string $type): void
    {
        $botUser = $this->resolveBotUser($botItem, $chatId, $type);
        BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $this->motherId($botItem))
            ->delete();
    }
}
