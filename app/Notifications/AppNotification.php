<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * One generic in-app (database) notification: title, message, link, icon and colour for the header bell.
 */
class AppNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
        public string $icon = 'bell',
        public string $color = 'primary',
        public string $kind = 'general',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'icon' => $this->icon,
            'color' => $this->color,
            'kind' => $this->kind,
        ];
    }
}
