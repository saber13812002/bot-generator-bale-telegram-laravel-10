<?php

namespace Tests\Fakes;

use App\Interfaces\Services\ChannelPosterPublisher;

class FakeChannelPosterPublisher implements ChannelPosterPublisher
{
    public array $privateMessages = [];
    public array $testMessages = [];
    public array $publishes = [];
    public array $callbacks = [];
    public bool $testSendSucceeds = true;
    public bool $publishSucceeds = true;
    /** @var list<string> Platforms that should always fail to publish (e.g. ['bale']). */
    public array $failPlatforms = [];

    public function sendPrivateMessage(string $chatId, string $text, ?array $inlineKeyboardRows = null): void
    {
        $this->privateMessages[] = [
            'chat_id' => $chatId,
            'text' => $text,
            'keyboard' => $inlineKeyboardRows,
        ];
    }

    public function sendTestMessage(string $channelChatId, string $text, string $platform = 'bale', ?string $botToken = null): bool
    {
        $this->testMessages[] = [
            'channel_chat_id' => $channelChatId,
            'text' => $text,
            'platform' => $platform,
            'bot_token' => $botToken,
        ];

        return $this->testSendSucceeds;
    }

    public function publish(
        string $channelChatId,
        string $contentType,
        ?string $text,
        ?string $fileId,
        string $platform = 'bale',
        ?string $botToken = null
    ): array {
        $this->publishes[] = [
            'channel_chat_id' => $channelChatId,
            'content_type' => $contentType,
            'text' => $text,
            'file_id' => $fileId,
            'platform' => $platform,
            'bot_token' => $botToken,
        ];

        $succeeds = $this->publishSucceeds && !in_array($platform, $this->failPlatforms, true);

        return ['success' => $succeeds, 'message_id' => $succeeds ? 'fake-msg-' . count($this->publishes) : null];
    }

    public function answerCallback(?string $callbackId, string $text = ''): void
    {
        $this->callbacks[] = [
            'callback_id' => $callbackId,
            'text' => $text,
        ];
    }

    public function lastPrivateText(): ?string
    {
        $last = end($this->privateMessages);

        return $last['text'] ?? null;
    }
}
