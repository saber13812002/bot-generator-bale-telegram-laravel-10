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
        $prefix = trans('growth_companion.today')."\n\n";
        $oldNeedle = trans('growth_companion.today');
        $classified = [];
        foreach ($this->messages as $message) {
            $text = (string) ($message['text'] ?? '');
            $isQuestion = str_starts_with($text, $prefix);
            $oldMatch = str_contains($text, $oldNeedle);
            if ($isQuestion) {
                $count++;
            }
            $classified[] = [
                'preview' => mb_substr($text, 0, 60),
                'oldMatch' => $oldMatch,
                'isQuestion' => $isQuestion,
            ];
        }
        // #region agent log
        file_put_contents(base_path('debug-3f8f5f.log'), json_encode([
            'sessionId' => '3f8f5f',
            'hypothesisId' => 'A',
            'location' => 'tests/Fakes/FakeGrowthMessenger.php:questionMessageCount',
            'message' => 'question vs board classification',
            'data' => ['count' => $count, 'classified' => $classified],
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
        // #endregion

        return $count;
    }
}
