<?php

namespace App\Models;

use App\Domain\Notifications\Enums\EmailLogStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class EmailLog extends Model
{
    use HasFactory;

    protected $table = 'email_logs';

    protected $fillable = [
        'to_email',
        'subject',
        'notification_type',
        'template',
        'payload',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'status' => EmailLogStatus::class,
        'sent_at' => 'datetime',
    ];
}
