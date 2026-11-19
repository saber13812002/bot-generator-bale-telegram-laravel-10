# Platform API Research — Soroush Plus & Rubika

**Phase 2 deliverable.** Every endpoint, field, and response shape below was verified against
official documentation downloaded on 2026-10-19. Nothing in this document is guessed. Items
that could not be verified are explicitly marked `BLOCKED (unverified)` — they must NOT be
implemented as if they were true until a live check (Phase 7) or stronger evidence says so.

Sources (official, downloaded locally for the record):

| Platform | Official source | Local artifact |
|---|---|---|
| Soroush Plus | `https://soroushplus.com/p/documents/bot-platform` (decoded from the page's Next.js flight payload) | `C:\Users\s.tabatabaei\soroush_doc.txt` (2409 lines of the full doc) |
| Rubika | `https://rubika.ir/botapi` (intro), `https://rubika.ir/botapi/methods`, `https://rubika.ir/botapi/models`, `https://rubika.ir/botapi/groupchannel` | `rubika_botapi.txt`, `rubika_methods.txt`, `rubika_models.txt`, `rubika_groupchannel.txt` in the same folder |

---

## 1. Soroush Plus — VERIFIED

### 1.1 Bot creation and token

- Bots are created at `splus.ir/botfather` (official BotFather inside Soroush Plus).
- Token format: `123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11` — **identical shape to Telegram tokens**.
  Consequence: the existing [`TokenHelper::isToken()`](app/Helpers/TokenHelper.php) Telegram-style
  regex `/^[0-9]{7,10}:[a-zA-Z0-9_-]{30,}/` is compatible (to be confirmed with a real token in Phase 7).

### 1.2 Request base and transport

- All requests go over **HTTPS** to:

  ```
  https://api.splus.ir/bot<token>/METHOD_NAME
  ```

- Both **GET and POST** are supported. Method names are **case-insensitive**.
- Parameter passing: URL query string, `application/json`, `application/x-www-form-urlencoded`,
  and `multipart/form-data` (file uploads).
- Response on success: a JSON object containing the result (Telegram-style envelope
  `{ "ok": true, "result": ... }`; errors carry `description` / `error_code`).

### 1.3 Methods documented (extracted from the official doc)

| Method | Purpose | Key verified parameters / notes |
|---|---|---|
| `getMe` | Bot info | returns `User` |
| `logOut`, `close` | Session management | — |
| `getUpdates` | Long polling | `offset`, `limit`, `timeout`, `allowed_updates`; updates stored ~24h (Telegram-style); recompute `offset` after each batch |
| `setWebhook` | Webhook | `url`, `certificate` (InputFile, self-signed certs must be uploaded as file), `ip_address`, `max_connections`, `allowed_updates` (JSON list, empty list = all except `chat_member`, `message_reaction`, `message_reaction_count`), `drop_pending_updates`. **Supported webhook ports: 443, 80, 88, 8443.** URL must be internet-reachable and hard to guess (docs recommend embedding the bot token in the path, e.g. `https://example.com/webhook/<BOT_TOKEN>`). While a webhook is set, `getUpdates` cannot be used. |
| `deleteWebhook` | Remove webhook | — |
| `getWebhookInfo` | Webhook status | returns `WebhookInfo`; empty `url` if the bot uses `getUpdates` |
| `sendMessage` | Text | `chat_id` = **Integer or `@username`**; `text` **1–4096 chars** after entities parsing; `parse_mode` (MarkdownV2 / HTML / legacy Markdown); `reply_markup`; `reply_to_message_id`; `allow_sending_without_reply`. Direct channel messages, linked chats and channel conversations are NOT supported as `chat_id` — use the channel's `@username`. |
| `sendPhoto` | Photo | `file_id` string (recommended) / HTTP URL / `attach://<name>` multipart. Max **10 MB**; width+height ≤ 10000 px; aspect ratio ≤ 20. |
| `sendVideo` | Video | same 3-source file model; other files max **50 MB**; optional `thumb` (JPEG < 200 kB, ≤ 320 px) |
| `sendAudio`, `sendVoice`, `sendDocument`, `sendAnimation`, `sendVideoNote` | Media | same file model (`file_id` / URL / `attach://`); `sendVideoNote` by URL is not supported |
| `sendSticker` | Sticker | WEBP/TGS/WEBM; video/animated stickers cannot be sent by HTTP URL |
| `sendMediaGroup`, `sendLocation`, `sendContact` | Misc | `sendContact` does **not** support channel chats |
| `forwardMessage`, `copyMessage` | Forwarding | Telegram-style |
| `editMessageText`, `editMessageCaption`, `editMessageMedia`, `editMessageReplyMarkup` | Editing | `editMessageText` new text 1–4096 chars |
| `deleteMessage` | Delete | — |
| `getFile` | File info | **max 20 MB download**; download via `https://api.splus.ir/file/bot<token>/<file_path>`; link valid **at least 1 hour**, refresh by calling `getFile` again |
| `answerCallbackQuery` | Inline keyboards | Telegram-style |
| `setMyCommands`, `getMyCommands`, `deleteMyCommands` | Commands | — |

`sendPoll` was **not found** in the decoded method list — treat poll support on Soroush as
`BLOCKED (unverified)`.

### 1.4 File / media model (important for our sync engine)

- Files already on Soroush servers are re-sent by `file_id` only — **no size limit** on re-send.
- New uploads: `multipart/form-data` via `attach://<file_attach_name>`; photos ≤ 10 MB, other files ≤ 50 MB.
- `file_id`s are **per-bot** (Telegram semantics): a `file_id` obtained in one chat cannot be used for another bot — matches the existing [`TelegramChannelPosterPublisher::tryFileId()`](app/Services/TelegramChannelPosterPublisher.php) same-platform-only design.

### 1.5 Chats

- Chat types: `private`, `group`, `supergroup`, **`channel`** — channel posting is a first-class citizen.
- For sending to a channel, the bot must be an admin of the channel (standard practice, as with Telegram/Bale).

### 1.6 Soroush — open items (`BLOCKED (unverified)`)

| Item | Why it matters | How to resolve |
|---|---|---|
| Public post-permalink URL format (e.g. `soroush.chat/{user}/{msgId}`?) | [`PostUrlBuilder::build()`](app/Services/ContentSync/PostUrlBuilder.php) currently returns `null`/`unverified` for soroush | Live check with a real Soroush channel in Phase 7 |
| Whether `chat_id` integer or `@username` works best for channel sends | Destination storage keying | Live test in Phase 7 (docs allow both) |
| Vendor SDK compatibility (`saber13812002/telegram-bale-bot-php`) — does it support a custom base URL? | Decides raw-HTTP vs SDK reuse in Phase 3 | Inspect vendor source in Phase 3 |
| Error envelope exact shape (`error_code` vs `description`) | Error mapping | Live test / doc re-read in Phase 7 |

---

## 2. Rubika — VERIFIED

### 2.1 Bot creation and token

- Bots are created by contacting **`BotFather@`** inside Rubika; it issues an API token.
- Token format: **not documented** (only used as `{token}` in URLs). Treat as an opaque string.
  Consequence: `TokenHelper::isToken()` cannot validate Rubika tokens — Phase 3 must accept a
  plain non-empty string for Rubika (with length sanity check), not a regex.

### 2.2 Request base and transport

- All methods: **POST** to:

  ```
  https://botapi.rubika.ir/v3/{token}/{method}
  ```

  with `Content-Type: application/json` (JSON body).
- A uniform success/error envelope is **not documented** — each method page only documents its
  output fields. `BLOCKED (unverified)`: exact error shape. Phase 7 must capture a real error.

### 2.3 Methods documented

| Method | Input (verified) | Output (verified) |
|---|---|---|
| `getMe` | — | `bot` object: `bot_id`, `bot_title`, `avatar` (File), `description`, `username`, `start_message`, `share_url` |
| `sendMessage` | `chat_id` (string), `text` (string), `chat_keypad_type` (`None`/`New`/`Remove`), `chat_keypad` (Keypad), `inline_keypad` (Keypad), `disable_notification` (bool, default false), `reply_to_message_id` (string) | `message_id` (string) |
| `sendPoll` | `question`, `options` (+ poll fields) | `message_id` |
| `sendLocation`, `sendContact` | chat/location/contact fields | `message_id` |
| `getChat` | `chat_id` | chat info |
| `getUpdates` | `offset_id` (string — pass the previous `next_offset_id`), `limit` (int) | `updates` (list[Update]), `next_offset_id` (string) |
| `forwardMessage` | `from_chat_id`, `message_id`, `to_chat_id`, `disable_notification` | `new_message_id` (string) |
| `editMessageText` | bot-sent message only (group restriction) | — |
| `editMessageKeypad` | message_id + keypad | — |
| `deleteMessage` | `chat_id`, `message_id` | — |
| `setCommands` | `bot_commands` list of `{command, description}` | — |
| `updateBotEndpoints` | `url` (string), `type` (`UpdateEndpointTypeEnum`: `ReceiveUpdate`, `ReceiveInlineMessage`, `ReceiveQuery`, `GetSelectionItem`, `SearchSelectionItems`) | — |
| `editChatKeypad` | chat keypad management | — |
| `getFile` | `file_id` (string) | `download_url` (string) |
| `sendFile` | `chat_id`, `file_id`, `text`, `reply_to_message_id`, `disable_notification`, `chat_keypad`, `inline_keypad`, `chat_keypad_type` | `message_id` (string) |
| `requestSendFile` | `type` (`FileTypeEnum`) | `upload_url` (string) |
| `banChatMember`, `unbanChatMember` | `chat_id`, `user_id` | — |

Note: the intro page calls the webhook method `updateBotEndpoint` (singular) while the methods
reference lists `updateBotEndpoints` (plural). The methods page is the normative API reference;
verify the live name in Phase 7.

### 2.4 Media upload flow (two-step, verified)

Rubika does **not** accept direct multipart media sends like Telegram/Bale/Soroush. The flow is:

1. `POST /v3/{token}/requestSendFile` with `{"type": "Image"}` (or other `FileTypeEnum`)
   → returns `upload_url`.
2. `POST` the file to that `upload_url` as **`multipart/form-data` with a single `file` field**
   (raw file in the body) → returns `file_id`.
3. `POST /v3/{token}/sendFile` with `{chat_id, file_id, text?}` → `message_id`.

`FileTypeEnum` limits (verified from the models page):

| Type | Format | Max size |
|---|---|---|
| `File` | generic | 50 MB |
| `Image` | jpg, gif, png, webp | 10 MB |
| `Voice` | mp3 | — (not stated) |
| `Video` | mp4 | 50 MB |
| `Music` | mp3 | — (not stated) |
| `Gif` | mp4, must be silent | — (not stated) |

This two-step flow means Rubika needs its own upload path in
[`ChannelPosterMediaSender::doUpload()`](app/Services/ChannelPosterMediaSender.php) — it cannot
reuse the Telegram-style `send{Type}` endpoints.

### 2.5 Receiving updates (two modes, verified)

**Mode A — long polling (`getUpdates`):**
- Poll every ~5 s, passing the previous `next_offset_id` as `offset_id`.
- Latency is bounded by the polling interval (docs state this explicitly).

**Mode B — webhook (`updateBotEndpoints`):**
- Requires a **public domain with valid SSL (HTTPS)**; the platform connects only to secure URLs.
- Each event type has its own endpoint `type`. For channel/content use the relevant ones are:
  - `ReceiveUpdate` → POST body `{ "update": { Update } }`
  - `ReceiveInlineMessage` → POST body `{ "inline_message": { InlineMessage } }`
- If the endpoint fails or returns an invalid response, the platform may **temporarily stop
  sending events** (docs warning).

Example `receiveUpdate` body (verified verbatim from the intro page):

```json
{
  "update": {
    "type": "NewMessage",
    "chat_id": "{chat_id}",
    "new_message": {
      "message_id": "{message_id}",
      "text": "custom text",
      "time": "1643122902",
      "is_edited": false,
      "sender_type": "User",
      "sender_id": "{sender_id}",
      "aux_data": { "start_id": null, "button_id": "{button_id}" }
    }
  }
}
```

### 2.6 Models (verified from the models page)

- `Update`: `type` (`NewMessage` / `UpdatedMessage` / `RemovedMessage` / `StartedBot` / `StoppedBot` / `EventData`), `chat_id`, `removed_message_id`, `new_message` (Message), `updated_message`, `event_data` (Event).
- `Message`: `message_id`, `text`, `time` (int timestamp), `is_edited`, `sender_type` (`User`/`Bot`), `sender_id`, `aux_data` (`start_id`, `button_id`), `file`, `reply_to_message_id`, `forwarded_from`, `location`, `sticker`, `contact_message`, `poll`.
- `Keypad`: `rows` (list of rows of buttons `{id, type, button_text}`), `resize_keyboard`, `one_time_keyboard`.
- Chat types (verified enum): `User`, `Bot`, `Group`, **`Channel`** — channels are first-class.

### 2.7 Groups & channels rules (verified from the group/channel page)

- The bot must be **added manually by an admin and promoted to admin** (no invite-link joining).
- **Max 10 admin bots** per group/channel at the same time.
- By default the bot only sees messages where it is **mentioned** or that **start with `/`**.
  To see **all** messages, the "receive all channel/group messages" option must be enabled in
  Rubika's `@BotFather`.
- `editMessageText` in groups only works on messages the bot itself sent.
- Bots only receive edit/delete events for changes made by **human** users (not other bots).

### 2.8 Rubika — open items (`BLOCKED (unverified)`)

| Item | Why it matters | How to resolve |
|---|---|---|
| `chat_id` format for channels (username vs numeric id) | Destination storage + `sendMessage` | Live check in Phase 7 |
| Success/error response envelope | Error mapping, `parseResponse()` | Capture a real call in Phase 7 |
| Max `text` length for `sendMessage` | Chunker limits | Live test in Phase 7 |
| `file_id` scope (per-bot? cross-chat?) | Whether a `file_id` from `requestSendFile` can be reused in `sendFile` to any chat | Live test in Phase 7 |
| Exact live webhook method name (`updateBotEndpoint` vs `updateBotEndpoints`) | Phase 4 webhook registration | Live test in Phase 7 |
| Public post-permalink URL format for `PostUrlBuilder` | Sync reports / dedup | Live check in Phase 7 |

---

## 3. Impact on integration (seams identified for Phase 3–6)

1. **Soroush is a near-drop-in Telegram clone.** `api.splus.ir/bot<token>/...` mirrors the
   Telegram URL scheme, so the existing publisher/media-sender branches can be extended with a
   `soroush` case pointing at `https://api.splus.ir` (or the vendor SDK if it accepts a custom
   base URL — to verify). Webhook ports 443/80/88/8443 must be honored by our endpoint config.
2. **Rubika needs a dedicated client** (`RubikaBotClient`): JSON-only POST, two-step file
   upload (`requestSendFile` → upload → `sendFile`), `offset_id`-based polling, and a webhook
   registration per event type. It cannot reuse the vendor SDK or the Telegram-style sender.
3. **Platform-key normalization:** the repo currently uses **two different keys for Soroush** —
   `soroush` (channel-poster/sync stack: [`ChannelPosterDestination::PLATFORM_SOROUSH`](app/Models/ChannelPosterDestination.php),
   [`PostUrlParser`](app/Services/ContentSync/PostUrlParser.php), admin validation
   `in:bale,telegram,eitaa,soroush`) and `soroosh` (legacy DB enums in migrations,
   [`config/bot.php`](config/bot.php) commented line, `BotRequest` rule
   `in:['bale','telegram','soroosh','gap']`). Phase 3 standardizes on **`soroush`** for all new
   code; Phase 6 adds a backward-compatible migration for the legacy enums (add value, do not
   drop the old one). Rubika is introduced as **`rubika`** everywhere.
4. **Token validation:** Soroush tokens match the Telegram regex; Rubika tokens do not have a
   documented shape — `BotRegistrationService` needs a per-platform validation strategy
   (regex for telegram/bale/soroush, loose non-empty check for rubika).
5. **Text limits for the chunker:** Soroush text = 4096 (same as Telegram), captions ≤ 1024
   (Telegram-style). Rubika text limit is undocumented → keep the conservative existing limit
   and flag it.
6. **Webhook security (both platforms):** docs for both recommend a non-guessable webhook path
   (embed the token). Our `WebhookEndpointHelper` URL generation already appends a per-bot path
   segment — this should include a secret for soroush/rubika endpoints as well.

---

## 4. What Phase 3 may NOT assume

- No live calls were made in this phase. **Nothing here is `LIVE_TESTED`.**
- Rubika's response envelope, channel `chat_id` shape, and text limits are `BLOCKED (unverified)`
  until Phase 7 runs a live smoke test (only against the operator's own test channels).
- Soroush post-permalink format remains `unverified` in `PostUrlBuilder` until Phase 7.
