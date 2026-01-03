<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewRssPostItemTranslationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $post;

    public function __construct(Post $post)
    {
        $this->post = $post;
    }

    public function via($notifiable)
    {
        return [RocketChatChannel::class];
    }

    public function toRocketChat($notifiable)
    {
        return (object) [
            'content' => "A new post has been created!",
            'title' => $this->post->title,
            'content' => $this->post->content,
        ];
    }
}
