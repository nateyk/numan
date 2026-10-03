<?php

namespace App\Notifications;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class FreightPostsHiddenNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Workspace $workspace,
        public int $hiddenCount,
        public int $visibleRemaining,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'freight_posts_hidden',
            'workspace_id' => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
            'hidden_count' => $this->hiddenCount,
            'visible_remaining' => $this->visibleRemaining,
            'message' => $this->hiddenCount === 1
                ? '1 freight post was hidden because your plan ended.'
                : "{$this->hiddenCount} freight posts were hidden because your plan ended.",
            'url' => '/billing',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
