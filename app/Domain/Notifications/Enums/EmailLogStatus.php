<?php

namespace App\Domain\Notifications\Enums;

enum EmailLogStatus: string
{
    case PENDING = 'PENDING';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
}
