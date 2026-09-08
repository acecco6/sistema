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
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('fixed_reservation_slot_id')
                ->nullable()
                ->after('court_id')
                ->constrained('fixed_reservation_slots')
                ->nullOnDelete();

            /*
             * Fecha lógica de esta ocurrencia.
             *
             * Es importante mantenerla incluso si la reserva
             * posteriormente queda CANCELLED.
             */
            $table->date('recurrence_date')
                ->nullable()
                ->after('fixed_reservation_slot_id');

            /*
             * Garantiza idempotencia del generador.
             *
             * Una ocurrencia de un slot para una fecha
             * solamente puede existir una vez.
             */
            $table->unique([
                'fixed_reservation_slot_id',
                'recurrence_date',
            ], 'reservation_fixed_occurrence_unique');

            $table->index([
                'fixed_reservation_slot_id',
                'recurrence_date',
            ], 'reservation_fixed_occurrence_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique(
                'reservation_fixed_occurrence_unique'
            );

            $table->dropIndex(
                'reservation_fixed_occurrence_index'
            );

            $table->dropConstrainedForeignId(
                'fixed_reservation_slot_id'
            );

            $table->dropColumn('recurrence_date');
        });
    }
};
