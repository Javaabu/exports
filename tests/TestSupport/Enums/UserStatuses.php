<?php

namespace Javaabu\Exports\Tests\TestSupport\Enums;

enum UserStatuses: string
{
    case Approved = 'approved';
    case Pending = 'pending';
    case Banned = 'banned';
}
