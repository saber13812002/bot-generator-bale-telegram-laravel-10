<?php

namespace App\Interfaces\Services;

use App\Models\Bot;
use App\Models\MpContactAdmin;
use App\Models\MpContactAdminRequest;
use App\Models\MpContactPoll;
use App\Models\MpContactPollComment;
use App\Models\MpContactPollVote;
use App\Models\MpContactTicket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MpContactBotService
{
    public function ensurePrimaryAdmin(Bot $bot, string $origin): void;

    public function isAdmin(int $botId, string $chatId, string $origin): bool;

    public function isPrimaryAdmin(int $botId, string $chatId, string $origin): bool;

    public function findAdmin(int $botId, string $chatId, string $origin): ?MpContactAdmin;

    public function addAdmin(int $botId, string $chatId, string $origin, bool $isPrimary = false): MpContactAdmin;

    public function removeAdmin(int $botId, string $chatId, string $origin): bool;

    public function createAdminRequest(int $botId, string $chatId, string $origin): array;

    public function getPendingAdminRequests(int $botId): Collection;

    public function approveAdminRequest(int $requestId, int $botId): ?MpContactAdminRequest;

    public function rejectAdminRequest(int $requestId, int $botId): ?MpContactAdminRequest;

    public function createTicket(int $botId, string $chatId, string $origin, string $body): MpContactTicket;

    public function findTicketByTrackingCode(int $botId, string $code, ?string $chatId = null): ?MpContactTicket;

    public function paginateTickets(int $botId, int $page = 1, int $perPage = 10): LengthAwarePaginator;

    public function updateTicketStatus(int $ticketId, int $botId, string $status): ?MpContactTicket;

    public function createPoll(int $botId, string $body): MpContactPoll;

    public function updatePollBody(int $pollId, int $botId, string $body): ?MpContactPoll;

    public function togglePollActive(int $pollId, int $botId): ?MpContactPoll;

    public function findPoll(int $pollId, int $botId): ?MpContactPoll;

    public function paginatePolls(int $botId, int $page = 1, int $perPage = 5, bool $activeOnly = false): LengthAwarePaginator;

    public function vote(int $pollId, int $botId, string $chatId, string $origin, string $choice): array;

    public function getUserVote(int $pollId, string $chatId, string $origin): ?MpContactPollVote;

    public function createPollComment(int $pollId, int $botId, string $chatId, string $origin, string $body): ?MpContactPollComment;

    public function getPollResults(int $pollId, int $botId): ?array;

    public function generateTrackingCode(int $botId, string $type = 'ticket'): string;
}
