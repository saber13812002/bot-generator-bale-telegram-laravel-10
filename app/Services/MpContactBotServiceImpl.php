<?php

namespace App\Services;

use App\Interfaces\Services\MpContactBotService;
use App\Models\Bot;
use App\Models\MpContactAdmin;
use App\Models\MpContactAdminRequest;
use App\Models\MpContactPoll;
use App\Models\MpContactPollComment;
use App\Models\MpContactPollVote;
use App\Models\MpContactTicket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MpContactBotServiceImpl implements MpContactBotService
{
    public function ensurePrimaryAdmin(Bot $bot, string $origin): void
    {
        $ownerChatId = $origin === 'bale'
            ? $bot->bale_owner_chat_id
            : $bot->telegram_owner_chat_id;

        if (empty($ownerChatId)) {
            return;
        }

        $existing = MpContactAdmin::where('bot_id', $bot->id)
            ->where('chat_id', (string) $ownerChatId)
            ->where('origin', $origin)
            ->first();

        if ($existing) {
            if (!$existing->is_primary) {
                $existing->update(['is_primary' => true]);
            }
            return;
        }

        MpContactAdmin::create([
            'bot_id' => $bot->id,
            'chat_id' => (string) $ownerChatId,
            'origin' => $origin,
            'is_primary' => true,
        ]);
    }

    public function isAdmin(int $botId, string $chatId, string $origin): bool
    {
        return MpContactAdmin::where('bot_id', $botId)
            ->where('chat_id', (string) $chatId)
            ->where('origin', $origin)
            ->exists();
    }

    public function isPrimaryAdmin(int $botId, string $chatId, string $origin): bool
    {
        return MpContactAdmin::where('bot_id', $botId)
            ->where('chat_id', (string) $chatId)
            ->where('origin', $origin)
            ->where('is_primary', true)
            ->exists();
    }

    public function findAdmin(int $botId, string $chatId, string $origin): ?MpContactAdmin
    {
        return MpContactAdmin::where('bot_id', $botId)
            ->where('chat_id', (string) $chatId)
            ->where('origin', $origin)
            ->first();
    }

    public function addAdmin(int $botId, string $chatId, string $origin, bool $isPrimary = false): MpContactAdmin
    {
        return MpContactAdmin::updateOrCreate(
            [
                'bot_id' => $botId,
                'chat_id' => (string) $chatId,
                'origin' => $origin,
            ],
            ['is_primary' => $isPrimary]
        );
    }

    public function removeAdmin(int $botId, string $chatId, string $origin): bool
    {
        $admin = $this->findAdmin($botId, $chatId, $origin);
        if (!$admin || $admin->is_primary) {
            return false;
        }

        return (bool) $admin->delete();
    }

    public function createAdminRequest(int $botId, string $chatId, string $origin): array
    {
        if ($this->isAdmin($botId, $chatId, $origin)) {
            return ['ok' => false, 'reason' => 'already_admin'];
        }

        $pending = MpContactAdminRequest::where('bot_id', $botId)
            ->where('chat_id', (string) $chatId)
            ->where('origin', $origin)
            ->where('status', MpContactAdminRequest::STATUS_PENDING)
            ->first();

        if ($pending) {
            return ['ok' => false, 'reason' => 'pending', 'request' => $pending];
        }

        $request = MpContactAdminRequest::create([
            'bot_id' => $botId,
            'chat_id' => (string) $chatId,
            'origin' => $origin,
            'status' => MpContactAdminRequest::STATUS_PENDING,
        ]);

        return ['ok' => true, 'request' => $request];
    }

    public function getPendingAdminRequests(int $botId): Collection
    {
        return MpContactAdminRequest::where('bot_id', $botId)
            ->where('status', MpContactAdminRequest::STATUS_PENDING)
            ->orderByDesc('id')
            ->get();
    }

    public function approveAdminRequest(int $requestId, int $botId): ?MpContactAdminRequest
    {
        $request = MpContactAdminRequest::where('id', $requestId)
            ->where('bot_id', $botId)
            ->where('status', MpContactAdminRequest::STATUS_PENDING)
            ->first();

        if (!$request) {
            return null;
        }

        $this->addAdmin($botId, $request->chat_id, $request->origin, false);
        $request->update(['status' => MpContactAdminRequest::STATUS_APPROVED]);

        return $request->fresh();
    }

    public function rejectAdminRequest(int $requestId, int $botId): ?MpContactAdminRequest
    {
        $request = MpContactAdminRequest::where('id', $requestId)
            ->where('bot_id', $botId)
            ->where('status', MpContactAdminRequest::STATUS_PENDING)
            ->first();

        if (!$request) {
            return null;
        }

        $request->update(['status' => MpContactAdminRequest::STATUS_REJECTED]);

        return $request->fresh();
    }

    public function createTicket(int $botId, string $chatId, string $origin, string $body): MpContactTicket
    {
        return MpContactTicket::create([
            'bot_id' => $botId,
            'chat_id' => (string) $chatId,
            'origin' => $origin,
            'tracking_code' => $this->generateTrackingCode($botId, 'ticket'),
            'body' => $body,
            'status' => MpContactTicket::STATUS_PENDING,
        ]);
    }

    public function findTicketByTrackingCode(int $botId, string $code, ?string $chatId = null): ?MpContactTicket
    {
        $query = MpContactTicket::where('bot_id', $botId)
            ->where('tracking_code', strtoupper(trim($code)));

        if ($chatId !== null) {
            $query->where('chat_id', (string) $chatId);
        }

        return $query->first();
    }

    public function paginateTickets(int $botId, int $page = 1, int $perPage = 10): LengthAwarePaginator
    {
        return MpContactTicket::where('bot_id', $botId)
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, $page));
    }

    public function updateTicketStatus(int $ticketId, int $botId, string $status): ?MpContactTicket
    {
        $allowed = [
            MpContactTicket::STATUS_PENDING,
            MpContactTicket::STATUS_REVIEWING,
            MpContactTicket::STATUS_CLOSED,
        ];

        if (!in_array($status, $allowed, true)) {
            return null;
        }

        $ticket = MpContactTicket::where('id', $ticketId)->where('bot_id', $botId)->first();
        if (!$ticket) {
            return null;
        }

        $ticket->update(['status' => $status]);

        return $ticket->fresh();
    }

    public function createPoll(int $botId, string $body): MpContactPoll
    {
        return MpContactPoll::create([
            'bot_id' => $botId,
            'body' => $body,
            'is_active' => true,
        ]);
    }

    public function updatePollBody(int $pollId, int $botId, string $body): ?MpContactPoll
    {
        $poll = $this->findPoll($pollId, $botId);
        if (!$poll) {
            return null;
        }

        $poll->update(['body' => $body]);

        return $poll->fresh();
    }

    public function togglePollActive(int $pollId, int $botId): ?MpContactPoll
    {
        $poll = $this->findPoll($pollId, $botId);
        if (!$poll) {
            return null;
        }

        $poll->update(['is_active' => !$poll->is_active]);

        return $poll->fresh();
    }

    public function findPoll(int $pollId, int $botId): ?MpContactPoll
    {
        return MpContactPoll::where('id', $pollId)->where('bot_id', $botId)->first();
    }

    public function paginatePolls(int $botId, int $page = 1, int $perPage = 5, bool $activeOnly = false): LengthAwarePaginator
    {
        $query = MpContactPoll::where('bot_id', $botId)->orderByDesc('id');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->paginate($perPage, ['*'], 'page', max(1, $page));
    }

    public function vote(int $pollId, int $botId, string $chatId, string $origin, string $choice): array
    {
        $poll = $this->findPoll($pollId, $botId);
        if (!$poll || !$poll->is_active) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        if (!in_array($choice, [MpContactPollVote::CHOICE_AGREE, MpContactPollVote::CHOICE_DISAGREE], true)) {
            return ['ok' => false, 'reason' => 'invalid_choice'];
        }

        $existing = $this->getUserVote($pollId, $chatId, $origin);
        if ($existing) {
            return ['ok' => false, 'reason' => 'already_voted', 'vote' => $existing];
        }

        $vote = MpContactPollVote::create([
            'poll_id' => $pollId,
            'chat_id' => (string) $chatId,
            'origin' => $origin,
            'choice' => $choice,
        ]);

        return ['ok' => true, 'vote' => $vote];
    }

    public function getUserVote(int $pollId, string $chatId, string $origin): ?MpContactPollVote
    {
        return MpContactPollVote::where('poll_id', $pollId)
            ->where('chat_id', (string) $chatId)
            ->where('origin', $origin)
            ->first();
    }

    public function createPollComment(int $pollId, int $botId, string $chatId, string $origin, string $body): ?MpContactPollComment
    {
        $poll = $this->findPoll($pollId, $botId);
        if (!$poll || !$poll->is_active) {
            return null;
        }

        return MpContactPollComment::create([
            'poll_id' => $pollId,
            'bot_id' => $botId,
            'chat_id' => (string) $chatId,
            'origin' => $origin,
            'body' => $body,
            'tracking_code' => $this->generateTrackingCode($botId, 'comment'),
        ]);
    }

    public function getPollResults(int $pollId, int $botId): ?array
    {
        $poll = $this->findPoll($pollId, $botId);
        if (!$poll) {
            return null;
        }

        $agree = $poll->votes()->where('choice', MpContactPollVote::CHOICE_AGREE)->count();
        $disagree = $poll->votes()->where('choice', MpContactPollVote::CHOICE_DISAGREE)->count();
        $comments = $poll->comments()->count();

        return [
            'poll' => $poll,
            'total' => $agree + $disagree,
            'agree' => $agree,
            'disagree' => $disagree,
            'comments' => $comments,
        ];
    }

    public function generateTrackingCode(int $botId, string $type = 'ticket'): string
    {
        for ($i = 0; $i < 20; $i++) {
            $code = strtoupper(Str::random(8));
            $code = preg_replace('/[^A-Z0-9]/', 'A', $code) ?? $code;
            $code = substr(str_pad($code, 8, '0'), 0, 8);

            $exists = $type === 'comment'
                ? MpContactPollComment::where('bot_id', $botId)->where('tracking_code', $code)->exists()
                : MpContactTicket::where('bot_id', $botId)->where('tracking_code', $code)->exists();

            if (!$exists) {
                return $code;
            }
        }

        return strtoupper(substr(md5($botId . microtime(true) . $type), 0, 8));
    }
}
