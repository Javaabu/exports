<?php

namespace Javaabu\Exports\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Javaabu\Exports\Models\Export;

/**
 * Tells the user their export is ready, with a signed download link
 */
class ExportCompletedNotification extends Notification
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
        $message = (new MailMessage)
            ->subject(__('Your :name export is ready', ['name' => $this->export->name]))
            ->line(__('Your :name export with :count rows is ready to download.', [
                'name' => $this->export->name,
                'count' => number_format((int) $this->export->total_rows),
            ]));

        if ($url = $this->export->temporaryDownloadUrl()) {
            $message->action(__('Download'), $url);
        }

        if ($this->export->expires_at) {
            $message->line(__('The file will be deleted on :date.', [
                'date' => $this->export->expires_at->toFormattedDayDateString(),
            ]));
        }

        return $message;
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
            'rows' => $this->export->total_rows,
            'download_url' => $this->export->temporaryDownloadUrl(),
            'message' => __('Your :name export is ready', ['name' => $this->export->name]),
        ];
    }
}
