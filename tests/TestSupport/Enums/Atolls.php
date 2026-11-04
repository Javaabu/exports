<?php

namespace Javaabu\Exports\Tests\TestSupport\Enums;

enum Atolls: string
{
    case KAAFU = 'kaafu';
    case SEENU = 'seenu';

    public function getLabel(): string
    {
        return match ($this) {
            self::KAAFU => 'ކ',
            self::SEENU => 'ސ',
        };
    }
}
