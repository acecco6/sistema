<?php

namespace App\Domain\Notifications\Repositories;

use App\Domain\Notifications\Entities\EmailLog;

interface EmailLogRepository
{
    public function findById(int $id): ?EmailLog;

    public function save(EmailLog $emailLog): EmailLog;

    public function update(EmailLog $emailLog): EmailLog;
}
