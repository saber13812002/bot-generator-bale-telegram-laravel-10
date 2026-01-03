<?php

namespace App\Services;

use App\Models\JiraTask;
use App\Models\RssChannel;
use Exception;

class RssBotCommandService
{
    public function __construct()
    {
        //
    }

    /**
     * @throws Exception
     */
    public function handleCommand(string $command): void
    {
        [$action, $service, $parameter] = explode(':', $command);

        switch ($action) {
            case 'publish':
                $this->handlePublish($service, $parameter);
                break;

            case 'create_task':
                $this->handleCreateTask($service, $parameter);
                break;

            case 'translate':
                $this->handleTranslate($service, $parameter);
                break;

            default:
                throw new Exception("Unknown command action: $action");
        }
    }

    protected function handlePublish($service, $channelName)
    {
        $channel = RssChannel::where('name', $service)->first();
        if (!$channel) {
            throw new Exception("Channel not found: $service");
        }

        // Example of handling a Telegram publish
        if ($service == 'telegram') {
            $channelDetail = $channel->details()->where('channel_name', $channelName)->first();
            if (!$channelDetail) {
                throw new Exception("Channel detail not found: $channelName");
            }
            // Publish logic for telegram
            $this->publishToTelegram($channelDetail);
        }
    }

    protected function handleCreateTask($service, $estimateDuration)
    {
        if ($service == 'jira') {
            JiraTask::create([
                'service_type' => 'jira',
                'estimate_duration' => $estimateDuration,
                // Add more task details as necessary
            ]);
            // Additional logic to send task to Jira
        }
    }

    protected function handleTranslate($service, $personName)
    {
        // Translate logic implementation
    }

    protected function publishToTelegram($channelDetail)
    {
        // Logic to publish to Telegram
    }
}
