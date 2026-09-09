<?php

use App\Jobs\CompleteFinishedReservationsJob;
use App\Jobs\ExpirePendingReservationsJob;
use App\Jobs\GenerateFixedReservationOccurrencesJob;
use Illuminate\Support\Facades\Schedule;



Schedule::job(new ExpirePendingReservationsJob())->everyMinute();
Schedule::job(new CompleteFinishedReservationsJob())->everyMinute();
Schedule::job(new GenerateFixedReservationOccurrencesJob())
    ->dailyAt(config('reservations.fixed_occurrences_schedule'));
