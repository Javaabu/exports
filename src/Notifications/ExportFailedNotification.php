<?php

namespace Javaabu\Exports\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Javaabu\Exports\Models\Export;

/**
 * Tells the user their export failed
 */
class ExportFailedNotification extends Notification
{
    public function __construct(
        public Export $export,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return config('exports.notifications.channels', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject(__('Your :name export failed', ['name' => $this->export->name]))
            ->line(__('Your :name export could not be completed. Please try again, or contact us if it keeps failing.', [
                'name' => $this->export->name,
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'export' => $this->export->uuid,
            'name' => $this->export->name,
            'status' => $this->export->status->value,
            'message' => __('Your :name export failed', ['name' => $this->export->name]),
        ];
    }
}
