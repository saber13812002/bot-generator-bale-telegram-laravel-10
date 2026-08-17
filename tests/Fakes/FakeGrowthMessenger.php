<?php

namespace Tests\Fakes;

use App\Interfaces\Services\GrowthMessenger;

class FakeGrowthMessenger implements GrowthMessenger
{
    public array $messages = [];
    public array $callbacks = [];

    public function send(string $chatId, string $text, ?array $inlineKeyboardRows = null): void
    {
        $this->messages[] = [
            'chat_id' => $chatId,
            'text' => $text,
            'keyboard' => $inlineKeyboardRows,
        ];
    }

    public function answerCallback(?string $callbackId, string $text = ''): void
    {
        $this->callbacks[] = [
            'callback_id' => $callbackId,
            'text' => $text,
        ];
    }

    public function lastText(): ?string
    {
        $last = end($this->messages);

        return $last['text'] ?? null;
    }
}
