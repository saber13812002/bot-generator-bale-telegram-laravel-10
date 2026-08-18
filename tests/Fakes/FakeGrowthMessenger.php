<?php

namespace Tests\Fakes;

use App\Interfaces\Services\GrowthMessenger;

class FakeGrowthMessenger implements GrowthMessenger
{
    public array $messages = [];
    public array $callbacks = [];
    public array $edits = [];

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

    public function editReplyMarkup(string $chatId, string|int $messageId, array $inlineKeyboardRows): bool
    {
        $this->edits[] = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'keyboard' => $inlineKeyboardRows,
        ];

        return true;
    }

    public function lastText(): ?string
    {
        $last = end($this->messages);

        return $last['text'] ?? null;
    }

    public function lastKeyboard(): ?array
    {
        $lastMsg = $this->messages !== [] ? end($this->messages) : null;
        if (is_array($lastMsg) && !empty($lastMsg['keyboard'])) {
            return $lastMsg['keyboard'];
        }

        $lastEdit = $this->edits !== [] ? end($this->edits) : null;

        return $lastEdit['keyboard'] ?? ($lastMsg['keyboard'] ?? null);
    }

    public function lastCallbackText(): ?string
    {
        $last = end($this->callbacks);

        return $last['text'] ?? null;
    }

    public function questionMessageCount(): int
    {
        $count = 0;
        foreach ($this->messages as $message) {
            $text = (string) ($message['text'] ?? '');
            if (str_contains($text, trans('growth_companion.today'))) {
                $count++;
            }
        }

        return $count;
    }
}
