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
        Schema::create('fixed_reservation_conflicts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fixed_reservation_id')
                ->constrained('fixed_reservations')
                ->cascadeOnDelete();

            $table->foreignId('fixed_reservation_slot_id')
                ->constrained('fixed_reservation_slots')
                ->cascadeOnDelete();

            $table->foreignId('court_id')
                ->constrained('courts');

            $table->date('recurrence_date');

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->string('reason', 100);

            $table->string('message', 255)
                ->nullable();

            $table->boolean('resolved')
                ->default(false);

            $table->foreignId('resolved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('resolved_at')
                ->nullable();

            $table->timestamps();

            /*
             * Una occurrence puede generar un solo conflicto.
             *
             * El Job puede correr 100 veces y no genera
             * 100 alertas iguales.
             */
            $table->unique([
                'fixed_reservation_slot_id',
                'recurrence_date',
            ])->name('unique_conflict');

            $table->index([
                'fixed_reservation_id',
                'resolved',
            ]);

            $table->index([
                'court_id',
                'recurrence_date',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_reservation_conflicts');
    }
};
