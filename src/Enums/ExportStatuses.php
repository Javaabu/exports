<?php

namespace Javaabu\Exports\Enums;

enum ExportStatuses: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::PROCESSING => __('Processing'),
            self::COMPLETED => __('Completed'),
            self::FAILED => __('Failed'),
            self::CANCELLED => __('Cancelled'),
        };
    }

    /**
     * Whether the export has stopped, successfully or not
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED], true);
    }

    /**
     * Whether the export can still be cancelled
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING], true);
    }
}
