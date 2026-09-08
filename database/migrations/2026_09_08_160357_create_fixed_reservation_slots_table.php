<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fixed_reservation_slots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fixed_reservation_id')
                ->constrained('fixed_reservations')
                ->cascadeOnDelete();

            $table->foreignId('court_id')
                ->constrained('courts');

            /*
             * ISO-8601:
             *
             * 1 = lunes
             * 2 = martes
             * ...
             * 7 = domingo
             */
            $table->unsignedTinyInteger('day_of_week');

            $table->time('start_time');

            $table->unsignedSmallInteger('duration_minutes');

            $table->boolean('active')->default(true);

            $table->timestamps();

            /*
             * Evita duplicar exactamente el mismo turno
             * dentro de la misma serie.
             */
            $table->unique([
                'fixed_reservation_id',
                'court_id',
                'day_of_week',
                'start_time',
            ], 'fixed_reservation_slot_unique');

            $table->index([
                'court_id',
                'day_of_week',
                'active',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_reservation_slots');
    }
};
