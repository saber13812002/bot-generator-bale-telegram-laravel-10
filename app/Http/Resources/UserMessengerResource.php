<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserMessengerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,

            'bale_channel_chat_id' => $this->bale_channel_chat_id,
            'bale_admin_chat_id' => $this->bale_admin_chat_id,
            'bale_bot_token' => $this->bale_bot_token,
            'bale_channel_invite_link' => $this->bale_channel_invite_link,

            'telegram_channel_chat_id' => $this->telegram_channel_chat_id,
            'telegram_admin_chat_id' => $this->telegram_admin_chat_id,
            'telegram_bot_token' => $this->telegram_bot_token,
            'telegram_channel_invite_link' => $this->telegram_channel_invite_link,

            'eitaa_channel_chat_id' => $this->eitaa_channel_chat_id,
            'eitaa_admin_chat_id' => $this->eitaa_admin_chat_id,
            'eitaa_bot_token' => $this->eitaa_bot_token,
            'eitaa_channel_invite_link' => $this->eitaa_channel_invite_link,
        ];
    }
}
