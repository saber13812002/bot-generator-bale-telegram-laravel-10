<?php

namespace App\Builders;

use App\Helpers\BotHelper;

class BotBuilder
{
    private $chat_id;
    private $photoUrl;
    private $audioUrl;
    private $title;
    private $messenger;
    private $caption;

    public function __construct($messenger)
    {
        $this->messenger = $messenger;
    }

    public function setChatId($chat_id)
    {
        $this->chat_id = $chat_id;
        return $this;
    }

    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function setPhotoUrl($photoUrl)
    {
        $this->photoUrl = $photoUrl;
        return $this;
    }

    public function setImageUrl($photoUrl)
    {
        $this->photoUrl = $photoUrl;
        return $this;
    }

    public function setAudioUrl($audioUrl): static
    {
        $this->audioUrl = $audioUrl;
        return $this;
    }

    public function setCaption($caption)
    {
        $this->caption = $caption;
        return $this;
    }

    public function sendPhoto()
    {
//        dd($this->messenger->BotType());
        if ($this->messenger->BotType() == 'eitaa') {
            return BotHelper::sendAnyFileMessageEitaa($this->chat_id, $this->photoUrl, $this->title, $this->messenger, $this->caption);
        } else if ($this->messenger->BotType() != 'gap') {
            return BotHelper::sendPhoto($this->chat_id, $this->photoUrl, $this->title, $this->messenger, $this->caption);
        } else {
            return BotHelper::sendPhotoGap($this->chat_id, $this->photoUrl, $this->messenger, $this->caption);
        }
    }


    public function sendAudio()
    {
//        dd($this->messenger->BotType());
        if ($this->messenger->BotType() == 'eitaa') {
            return BotHelper::sendAnyFileMessageEitaa($this->chat_id, $this->audioUrl, $this->title, $this->messenger, $this->caption);
        } else if ($this->messenger->BotType() != 'gap') {
            return BotHelper::sendAudio($this->chat_id, $this->audioUrl, $this->title, $this->messenger, $this->caption);
        }
//        else {
//            return BotHelper::sendAudioGap($this->chat_id, $this->audioUrl, $this->messenger, $this->caption);
//        }
    }
}
