<?php

use App\Jobs\CompleteFinishedReservationsJob;
use App\Jobs\ExpirePendingReservationsJob;

use Illuminate\Support\Facades\Schedule;



Schedule::job(new ExpirePendingReservationsJob())->everyMinute();
Schedule::job(new CompleteFinishedReservationsJob())->everyMinute();
