<?php

namespace App\Interfaces\Services;

use App\Models\ContentSubmissionItem;

interface ContentSubmissionService
{
    /**
     * Create and persist a content submission item.
     */
    public function submitContent(
        int $botId,
        int $submitterChatId,
        string $contentType,
        ?string $contentText = null,
        ?string $fileId = null,
        ?string $fileUniqueId = null
    ): ContentSubmissionItem;

    /**
     * Send item to approval group (text/photo/video).
     */
    public function sendToApprovalGroup(ContentSubmissionItem $item, string $type): bool;

    /**
     * Process a reply "1" in approval group. Returns the item if fully approved (ready to publish).
     */
    public function processApprovalReply(
        int $botId,
        int $groupChatId,
        int $replyToMessageId,
        int $approverChatId,
        string $type
    ): ?ContentSubmissionItem;

    /**
     * Publish item to channel.
     */
    public function publishToChannel(ContentSubmissionItem $item, string $type): bool;

    /**
     * Notify submitter of status (approved, rejected, published).
     */
    public function notifySubmitter(ContentSubmissionItem $item, string $status, string $type): void;
}
