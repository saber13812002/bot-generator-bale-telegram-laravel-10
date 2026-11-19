<?php

namespace Tests\Fakes;

use App\Interfaces\RssChannelMessageSender;
use Throwable;

/**
 * Fake for RssChannelMessageSender — records every call and returns
 * configurable (or exceptional) responses so tests can simulate:
 *  - successful base delivery,
 *  - base failure (e.g. 403),
 *  - partial media failure after base delivery,
 *  - hard exceptions.
 *
 * NOTE: the recorded arrays live in a STATIC registry on purpose. Jobs that
 * dispatch through the queue (even QUEUE_CONNECTION=sync) are serialized and
 * unserialized, which clones the injected sender instance — the test's own
 * instance would then see zero calls. The shared static registry keeps all
 * instances (original + deserialized copies) pointing at the same records.
 */
class FakeRssChannelMessageSender implements RssChannelMessageSender
{
    /** @var array<int, array<string, mixed>> */
    private static array $sharedBaseMessages = [];

    /** @var array<int, array<string, mixed>> */
    private static array $sharedPhotos = [];

    /** @var array<int, array<string, mixed>> */
    private static array $sharedAudios = [];

    public mixed $baseResponse = ['ok' => true, 'result' => ['message_id' => 'fake-base-1']];

    public mixed $photoResponse = ['ok' => true, 'result' => ['message_id' => 'fake-photo-1']];

    public mixed $audioResponse = ['ok' => true, 'result' => ['message_id' => 'fake-audio-1']];

    public ?Throwable $baseException = null;

    public ?Throwable $photoException = null;

    public ?Throwable $audioException = null;

    public function __construct()
    {
        // Each test gets a fresh app + fresh fake; reset the shared registry so
        // tests never see sends from a previous test in the same process.
        self::$sharedBaseMessages = [];
        self::$sharedPhotos = [];
        self::$sharedAudios = [];
    }

    /**
     * Property-style access (e.g. $sender->baseMessages) — proxies to the
     * shared static registry so deserialized job copies and the test's own
     * instance observe the same records.
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'baseMessages' => self::$sharedBaseMessages,
            'photos' => self::$sharedPhotos,
            'audios' => self::$sharedAudios,
            default => null,
        };
    }

    public function sendBaseMessage(string $message, string $token, $target_id, string $mediumSlug, ?string $parseMode = null): mixed
    {
        self::$sharedBaseMessages[] = [
            'message' => $message,
            'token' => $token,
            'target_id' => $target_id,
            'mediumSlug' => $mediumSlug,
            'parseMode' => $parseMode,
        ];

        if ($this->baseException !== null) {
            throw $this->baseException;
        }

        return $this->baseResponse;
    }

    public function sendPhoto(string $token, $target_id, string $photoUrl, string $caption, string $title, string $mediumSlug, ?string $parseMode = null): mixed
    {
        self::$sharedPhotos[] = [
            'token' => $token,
            'target_id' => $target_id,
            'photoUrl' => $photoUrl,
            'caption' => $caption,
            'title' => $title,
            'mediumSlug' => $mediumSlug,
            'parseMode' => $parseMode,
        ];

        if ($this->photoException !== null) {
            throw $this->photoException;
        }

        return $this->photoResponse;
    }

    public function sendAudio(string $token, $target_id, string $audioUrl, string $caption, string $title, string $mediumSlug, ?string $parseMode = null): mixed
    {
        self::$sharedAudios[] = [
            'token' => $token,
            'target_id' => $target_id,
            'audioUrl' => $audioUrl,
            'caption' => $caption,
            'title' => $title,
            'mediumSlug' => $mediumSlug,
            'parseMode' => $parseMode,
        ];

        if ($this->audioException !== null) {
            throw $this->audioException;
        }

        return $this->audioResponse;
    }
}
